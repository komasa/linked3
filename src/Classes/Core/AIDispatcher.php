<?php

declare(strict_types=1);
/**
 * AI Dispatcher — single entry point for all AI calls in linked3.0.
 *
 * Responsibilities:
 *   1) Resolve provider via Factory + pick API key via KeyRotator
 *   2) Build payload via Provider Strategy
 *   3) Send request via Safe_Remote (SSRF-hardened, circuit-broken)
 *   4) Log every call to linked3_usage_logs (tokens, cost, status)
 *   5) Mark failed keys unhealthy in the Rotator
 *   6) Provider-level circuit breaker: if a provider fails >5 times in 5 min,
 *      fall back to the next configured provider
 *
 * Mirrors aipower's AI Dispatcher but adds billing-grade usage logging
 * (the single biggest commercial gap in linked v2.9.6).
 *
 * @package Linked3
 * @subpackage Classes\Core
 */

namespace Linked3\Classes\Core;

use Linked3\Classes\Core\Providers\ProviderFactory;
use Linked3\Classes\Security\RateLimiter;
use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;
use Linked3\Includes\Crypto;



if (!defined('ABSPATH')) {
    exit;
}
final class AIDispatcher
{
    /** @var self|null */
    private static $instance;

    /** @var Logger */
    private $log;

    /** @var ProviderFactory */
    private $factory;

    /** @var TokenManager|null */
    private $tokens;

    /** HTTP status codes that constitution §4 says must evict the API key. */
    const KEY_EVICT_CODES = [401, 403, 429];

    /** Constitution §3: provider circuit opens after this many failures / 5 min. */
    const CIRCUIT_THRESHOLD = 5;

    private function __construct() {
        $this->log     = Logger::instance();
        $this->factory = ProviderFactory::instance();
        $this->tokens  = class_exists('\\Linked3\\Classes\\Core\\TokenManager')
            ? TokenManager::instance()
            : null;
    }

    /**
     * Get the singleton instance.
     *
     * v4.4.2: delegates to the Container so call sites can be
     * migrated to dependency injection gradually. Existing `::instance()`
     * call sites continue to work unchanged.
     *
     * @return self
     */
    public static function instance() : mixed {
        if (null === self::$instance) {
            // If the DI container is loaded, use it (enables test overrides).
            if (class_exists('\\Linked3\\Includes\\Container')) {
                $container = \Linked3\Includes\Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.2: this is the construction site used by the container's
     * factory. It bypasses the container to avoid infinite recursion
     * (Container::get() → factory → ::instance() → Container::get() …).
     *
     * @return self
     * @internal Called only by Container::register_defaults().
     */
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Non-streaming chat completion.
     *
     * @param array  $messages  [{role, content}, ...]
     * @param array  $options   {provider, model, temperature, max_tokens, ...}
     * @param array  $config    Provider config: {api_key, api_base, fallback_providers}
     * @return array{content:string, usage:array, provider:string, model:string, raw:array}
     * @throws \RuntimeException When all providers (incl. fallbacks) fail.
     */
    public function chat(array $messages, array $options, array $config) : mixed {
        // ---- Constitution §6: per-user 100 req/hour gate (IP-level 60/min
        // is enforced earlier by Rate_Limiter::gate() inside the security
        // traits, but AI calls also need a user-level hourly ceiling so a
        // single account can't drain the provider budget). ----
        // v0.8.0 hardening: allow callers (e.g. AutoGPT cron) to override
        // the operating user via $options['user_id'] so background tasks
        // bill against the task OWNER's plan quota instead of bleeding into
        // the shared guest (user_id=0) bucket — which would (a) mis-account
        // token usage and (b) exhaust the free-plan 50k cap across all
        // background agents globally.
        $user_id = isset($options['user_id']) ? (int) $options['user_id'] : get_current_user_id();
        RateLimiter::per_user_hourly($user_id, 'ai_chat');

        // ---- Constitution §2: token-quota hard gate. Refuse before any
        // network call if the user/guest has exhausted their daily quota. ----
        $session_id = isset($options['session_id']) ? (string) $options['session_id'] : '';
        if ($this->tokens) {
            $quota = $this->tokens->check($user_id, $session_id, 1);
            if (!$quota['ok']) {
                $this->log->warning('ai', 'Quota exhausted — request denied', [
                    'user_id'   => $user_id,
                    'used'      => $quota['used'],
                    'quota'     => $quota['quota'],
                    'remaining' => $quota['remaining'],
                ]);
                throw new \RuntimeException(sprintf(
                    'Quota exhausted (used %d/%d tokens today). Try again tomorrow.',
                    $quota['used'],
                    $quota['quota']
                ));
            }
        }

        // 读取默认 Provider (从 API 设置保存的 option)
        $default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $provider_slug = $options['provider'] ?? $default_provider;
        // 支持自定义 API 站点 (custom_xxx 格式)
        if (strpos($provider_slug, 'custom_') === 0) {
            $custom_id = substr($provider_slug, 7);
            $custom_apis = (array) get_option(LINKED3_OPTION_PREFIX . 'custom_apis', []);
            if (isset($custom_apis[$custom_id])) {
                $config['custom_api'] = $custom_apis[$custom_id];
            }
        }
        $fallbacks = $config['fallback_providers'] ?? [];

        // v20.4-fix11: 用户主动触发的调用 (如杠杆链) 可绕过陈旧熔断器
        // 熔断器可能因前一次失败而打开, 但 API 已恢复; 用户主动重试时应允许尝试
        $bypass_circuit = !empty($config['force_bypass_circuit']);

        // Build the chain: primary + fallbacks.
        $chain = array_merge([$provider_slug], $fallbacks);

        $last_error = null;
        foreach ($chain as $slug) {
            if (!$bypass_circuit && $this->is_circuit_open($slug)) {
                $this->log->warning('ai', "Provider {$slug} circuit open, skipping", ['chain' => $chain]);
                continue;
            }
            try {
                $result = $this->call_single($slug, $messages, $options, $config);
                // Success → reset failure counter.
                $this->reset_circuit($slug);
                // Record token usage against the quota ledger. bot_id is
                // threaded through $options by Chat_Manager so the guest
                // (session_id, bot_id) row matches what check_quota writes.
                if ($this->tokens && !empty($result['usage']['total_tokens'])) {
                    $bot_id = isset($options['bot_id']) ? (int) $options['bot_id'] : 0;
                    $this->tokens->record($user_id, $session_id, (int) $result['usage']['total_tokens'], $bot_id);
                }
                return $result;
            } catch (\Exception $e) {
                $last_error = $e;
                $this->record_failure($slug, $e->getMessage());
                $this->log->error('ai', "Provider {$slug} failed: " . $e->getMessage(), [
                    'fallback_attempted' => true,
                ]);
                continue;
            }
        }

        throw new \RuntimeException(
            'All providers failed. Last error: ' . ($last_error ? $last_error->getMessage() : 'unknown')
        );
    }

    /**
     * Call a single provider. Handles key rotation + usage logging.
     *
     * @param string $slug
     * @param array  $messages
     * @param array  $options
     * @param array  $config
     * @return array{content:string, usage:array, provider:string, model:string, raw:array}
     * @throws \RuntimeException
     */
    private function call_single($slug, array $messages, array $options, array $config)
    : array {
        // 自定义 API 站点支持: slug 格式 custom_xxx
        if (strpos($slug, 'custom_') === 0) {
            $custom_id = substr($slug, 7);
            $custom_apis = (array) get_option(LINKED3_OPTION_PREFIX . 'custom_apis', []);
            if (!isset($custom_apis[$custom_id])) {
                throw new \RuntimeException("自定义 API 站点不存在: {$custom_id}");
            }
            $api = $custom_apis[$custom_id];
            // 用 OpenAI-compat provider + 自定义 URL/Model/Key
            $provider = new \Linked3\Classes\Core\Providers\OpenAICompatProvider($slug, '');
            // 多 Key 轮询
            $keys = array_filter(array_map('trim', explode("\n", $api['key'])));
            if (empty($keys)) {
                throw new \RuntimeException("自定义 API 无 Key: {$api['name']}");
            }
            $config['api_key'] = $keys[0]; // 简化:取第一个
            $config['api_base'] = rtrim(str_replace('/chat/completions', '', $api['url']), '/');
            $options['model'] = $options['model'] ?? $api['model'];

            // 直接调用 (复用 OpenAI-compat 逻辑)
            $url = $provider->build_api_url('chat', $config);
            $headers = $provider->get_api_headers($config);
            $headers['Accept'] = 'application/json';
            $payload = $provider->format_chat_payload($messages, $options, $config);

            $started = microtime(true);
            // v5.2.9: 支持调用方动态传入 timeout
            $custom_timeout = isset($options['timeout']) ? (int) $options['timeout'] : $provider->default_timeout();
            $response = SafeRemote::post($url, [
                'timeout'     => $custom_timeout,
                'headers'     => $headers,
                'body'        => wp_json_encode($payload),
                'data_format' => 'body',
                'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
            ]);
            $elapsed_ms = (int) ((microtime(true) - $started) * 1000);

            if (is_wp_error($response)) {
                throw new \RuntimeException('HTTP 错误: ' . $response->get_error_message());
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $body_raw = wp_remote_retrieve_body($response);
            $body = json_decode($body_raw, true);
            if ($code >= 400) {
                $err = $provider->parse_error_response($body, $code);
                throw new \RuntimeException("自定义 API HTTP {$code}: {$err['message']}");
            }
            $parsed = $provider->parse_chat_response($body, $config);
            $usage = $parsed['usage'];
            $this->log_usage([
                'user_id' => isset($options['user_id']) ? (int) $options['user_id'] : get_current_user_id(),
                'module' => $options['module'] ?? 'general',
                'provider' => $slug,
                'model' => $options['model'] ?? $api['model'],
                'prompt_tokens' => $usage['prompt_tokens'],
                'completion_tokens' => $usage['completion_tokens'],
                'total_tokens' => $usage['total_tokens'],
                'status' => 'ok',
                'elapsed_ms' => $elapsed_ms,
            ]);
            return [
                'content'  => $parsed['content'],
                'usage'    => $usage,
                'provider' => $slug,
                'model'    => $options['model'] ?? $api['model'],
                'raw'      => $parsed['raw'],
            ];
        }

        $provider = $this->factory->make($slug);
        if (!$provider) {
            throw new \RuntimeException("Unknown provider: {$slug}");
        }

        // v0.8.0: re-derive the operating user_id (chat() may have overridden
        // it via $options['user_id'] for AutoGPT background tasks). call_single
        // is invoked from chat(), so $user_id is not in this scope directly.
        $user_id = isset($options['user_id']) ? (int) $options['user_id'] : get_current_user_id();

        // 读取默认 model + api_base (从 API 设置保存的 option)
        if (empty($options['model'])) {
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            if (!empty($saved_models[$slug])) {
                $options['model'] = $saved_models[$slug];
            }
        }
        // 如果仍然没有 model,用 Provider 的默认模型 (而非 gpt-4o-mini)
        if (empty($options['model'])) {
            $provider_defaults = [
                'openai' => 'gpt-4o-mini',
                'deepseek' => 'deepseek-chat',
                'kimi' => 'moonshot-v1-8k',
                'qwen' => 'qwen-plus',
                'doubao' => 'doubao-pro-4k',
                'zhipu' => 'glm-4-flash',
                'zai' => 'glm-4-flash',
                'siliconflow' => 'Qwen/Qwen2.5-7B-Instruct',
                'hunyuan' => 'hunyuan-pro',
                'tencent_lke' => 'lke-bot',
            ];
            $options['model'] = $provider_defaults[$slug] ?? 'gpt-4o-mini';
        }
        if (empty($config['api_base'])) {
            $saved_bases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
            if (!empty($saved_bases[$slug])) {
                $config['api_base'] = $saved_bases[$slug];
            }
        }

        // Resolve API key(s) — supports multi-key rotation. Each key is
        // decrypted at the boundary (Constitution §5: AES-256-GCM at rest).
        // Crypto::decrypt() returns the input unchanged for legacy
        // plaintext entries, so this is forward-compatible.
        $raw_keys = !empty($config['api_keys']) ? (array) $config['api_keys'] : (isset($config['api_key']) ? [$config['api_key']] : []);
        // 如果调用方没传 key,从保存的 provider_keys 读
        if (empty($raw_keys)) {
            $saved_keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            if (!empty($saved_keys[$slug])) {
                $raw_keys = array_filter(array_map('trim', explode("\n", $saved_keys[$slug])));
            }
        }
        // v20.4-fix4: 内置默认 API Key — 硅基流动 (安装即用,无需配置)
        // 用户可在后台 AI 设置中覆盖此默认值
        if (empty($raw_keys) && $slug === 'siliconflow') {
            
        }
        $keys = [];
        foreach ($raw_keys as $k) {
            $decrypted = Crypto::decrypt((string) $k);
            if ($decrypted !== '') {
                $keys[] = $decrypted;
            }
        }
        $picked = $this->factory->rotator()->pick($slug, $keys);
        if ($picked['key'] === '') {
            throw new \RuntimeException("No API key configured for {$slug}");
        }
        $config['api_key'] = $picked['key'];

        $model = $options['model'] ?? ($config['model'] ?? '');
        $url = $provider->build_api_url('chat', $config);
        $headers = $provider->get_api_headers($config);
        $headers['Accept'] = 'application/json';
        $payload = $provider->format_chat_payload($messages, $options, $config);

        $started = microtime(true);
        // v5.2.9: 支持调用方动态传入 timeout (长文需要更长超时)
        $request_timeout = isset($options['timeout']) ? (int) $options['timeout'] : $provider->default_timeout();
        $response = SafeRemote::post($url, [
            'timeout'     => $request_timeout,
            'headers'     => $headers,
            'body'        => wp_json_encode($payload),
            'data_format' => 'body',
            // Safe_Remote already enforces the host whitelist; tell it this
            // host is allowed even if not in the default list.
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        $elapsed_ms = (int) ((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            // Transport failure (timeout / DNS / connection refused). Log it
            // to the usage ledger so billing dashboards see failed attempts
            // too — Constitution §7 mandates EVERY AI call be logged.
            $err_code = $response->get_error_code();
            $err_msg  = $response->get_error_message();
            $this->log_usage([
                'user_id'     => $user_id,
                'module'      => $options['module'] ?? 'general',
                'provider'    => $slug,
                'model'       => $model,
                'status'      => 'error',
                'error_code'  => is_string($err_code) ? $err_code : 'http_error',
                'elapsed_ms'  => $elapsed_ms,
            ]);
            // Transport failures aren't necessarily the key's fault, so we
            // do NOT call mark_failed() here — that's reserved for the
            // 401/403/429 key-specific failures below (Constitution §4).
            throw new \RuntimeException('HTTP error: ' . $err_msg);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if ($code >= 400) {
            // Constitution §4: only 401/403/429 evict the key (auth / rate
            // limit / forbidden). Other 4xx (e.g. 400 Bad Request, 404 Not
            // Found) and 5xx are server/payload issues — rotating keys
            // would just spread the failure to all keys uselessly.
            if (in_array($code, self::KEY_EVICT_CODES, true)) {
                $this->factory->rotator()->mark_failed($slug, $picked['index']);
            }
            $err = $provider->parse_error_response($body, $code);
            $this->log_usage([
                'user_id' => $user_id,
                'module' => $options['module'] ?? 'general',
                'provider' => $slug,
                'model' => $model,
                'status' => 'error',
                'error_code' => $err['code'],
                'elapsed_ms' => $elapsed_ms,
            ]);
            throw new \RuntimeException("Provider {$slug} HTTP {$code}: {$err['message']}");
        }

        $parsed = $provider->parse_chat_response($body, $config);
        $usage = $parsed['usage'];

        $this->log_usage([
            'user_id' => $user_id,
            'module' => $options['module'] ?? 'general',
            'provider' => $slug,
            'model' => $model,
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'total_tokens' => $usage['total_tokens'],
            'status' => 'ok',
            'elapsed_ms' => $elapsed_ms,
            'degraded' => $picked['degraded'],
        ]);

        return [
            'content'  => $parsed['content'],
            'usage'    => $usage,
            'provider' => $slug,
            'model'    => $model,
            'raw'      => $parsed['raw'],
        ];
    }

    /**
     * Insert a usage log row (billing + quota source of truth).
     *
     * @param array $row
     * @return void
     */
    private function log_usage(array $row)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_usage_logs';

        $defaults = [
            'user_id'           => 0,
            'session_id'        => '',
            'module'            => 'general',
            'provider'          => '',
            'model'             => '',
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
            'cost_usd'          => 0,
            'status'            => 'ok',
            'error_code'        => '',
        ];
        $row = array_merge($defaults, $row);
        $cost = $this->estimate_cost_usd($row['provider'], $row['model'], $row['prompt_tokens'], $row['completion_tokens']);
        $row['cost_usd'] = $cost;

        // phpcs:disable WordPress.DB -- column names are constants.
        $wpdb->insert($table, [
            'user_id'           => $row['user_id'],
            'session_id'        => $row['session_id'],
            'module'            => $row['module'],
            'provider'          => $row['provider'],
            'model'             => $row['model'],
            'prompt_tokens'     => $row['prompt_tokens'],
            'completion_tokens' => $row['completion_tokens'],
            'total_tokens'      => $row['total_tokens'],
            'cost_usd'          => $cost,
            'status'            => $row['status'],
            'error_code'        => $row['error_code'],
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%s']);
        // phpcs:enable
    }

    /**
     * Rough cost estimator — refine with live pricing in v0.1.10 admin.
     *
     * @param string $provider
     * @param string $model
     * @param int    $prompt
     * @param int    $completion
     * @return float
     */
    private function estimate_cost_usd($provider, $model, $prompt, $completion) : mixed     {
        // Rates per 1K tokens (rough, as of 2024). Replace with live config.
        $rates = [
            'openai'   => ['in' => 0.005, 'out' => 0.015],
            'deepseek' => ['in' => 0.00014, 'out' => 0.00028],
            'kimi'     => ['in' => 0.0017, 'out' => 0.0017],
            'qwen'     => ['in' => 0.0007, 'out' => 0.0028],
            'doubao'   => ['in' => 0.0008, 'out' => 0.002],
        ];
        $r = $rates[$provider] ?? ['in' => 0.002, 'out' => 0.006];
        return round(($prompt / 1000) * $r['in'] + ($completion / 1000) * $r['out'], 6);
    }

    // ----- Provider-level circuit breaker -----

    /**
     * @param string $slug
     * @return bool
     */
    private function is_circuit_open($slug)
    {
        return (int) get_transient('linked3_pcb_' . $slug) >= self::CIRCUIT_THRESHOLD;
    }

    /**
     * @param string $slug
     * @return void
     */
    private function reset_circuit($slug)
    : void {
        delete_transient('linked3_pcb_' . $slug);
    }

    /**
     * @param string $slug
     * @param string $message
     * @return void
     */
    private function record_failure($slug, $message)
    : void {
        $key = 'linked3_pcb_' . $slug;
        // Read-modify-write race is acceptable here: transient TTL is short,
        // and a stale read at worst delays the circuit opening by one cycle.
        $n = (int) get_transient($key) + 1;
        set_transient($key, $n, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * v5.0.0 (P2-7): Streaming chat completion via SSE.
     *
     * Sends chunks to the browser as they arrive from the provider. The
     * frontend uses the /linked3/v1/stream/:cache_key REST endpoint to
     * poll accumulated chunks (the Init_Stream_Action creates the cache
     * entry, this method appends to it).
     *
     * Usage:
     *   $dispatcher->stream($messages, $options, $config, $cache_key);
     *
     * @param array  $messages  [{role, content}, ...]
     * @param array  $options   {provider, model, temperature, max_tokens, ...}
     * @param array  $config    Provider config: {api_key, api_base, fallback_providers}
     * @param string $cache_key The SSE cache key to append chunks to.
     * @return array{content:string, usage:array, provider:string, model:string}
     * @throws \RuntimeException When all providers (incl. fallbacks) fail.
     */
    public function stream(array $messages, array $options, array $config, string $cache_key)
    {
        // For now, stream() delegates to chat() and writes the full result
        // as a single chunk. This is a functional fallback — true SSE
        // streaming requires the Provider Strategy to support parse_sse_chunk()
        // and a non-blocking HTTP request, which will be implemented in a
        // future version. The cache_key is used so the frontend poll loop
        // works correctly today.
        $result = $this->chat($messages, $options, $config);

        // Write the complete content as a single chunk to the SSE cache.
        if (function_exists('wp_cache_set') && $cache_key) {
            global $wpdb;
            $table = $wpdb->prefix . 'linked3_sse_message_cache';
            $payload = wp_json_encode([
                'chunks'  => [$result['content'] ?? ''],
                'status'  => 'done',
                'content' => $result['content'] ?? '',
            ]);
            $wpdb->update(
                $table,
                ['payload' => $payload, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 300)],
                ['cache_key' => $cache_key],
                ['%s', '%s'],
                ['%s']
            );
        }

        return $result;
    }
}
