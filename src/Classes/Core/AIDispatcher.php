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
    private function call_single(string $slug, array $messages, array $options, array $config)
    : array {
        // Custom API site (slug format: custom_xxx)
        if (strpos($slug, 'custom_') === 0) {
            return $this->call_custom_api($slug, $messages, $options, $config);
        }

        return $this->call_standard_provider($slug, $messages, $options, $config);
    }

    /**
     * Handle custom API site call (slug format: custom_xxx).
     */
    private function call_custom_api(string $slug, array $messages, array $options, array $config) : array {
        $custom_id = substr($slug, 7);
        $custom_apis = (array) get_option(LINKED3_OPTION_PREFIX . 'custom_apis', []);
        if (!isset($custom_apis[$custom_id])) {
            throw new \RuntimeException("自定义 API 站点不存在: {$custom_id}");
        }
        $api = $custom_apis[$custom_id];
        $provider = new \Linked3\Classes\Core\Providers\OpenAICompatProvider($slug, '');
        $keys = array_filter(array_map('trim', explode("\n", $api['key'])));
        if (empty($keys)) {
            throw new \RuntimeException("自定义 API 无 Key: {$api['name']}");
        }
        $config['api_key'] = $keys[0];
        $config['api_base'] = rtrim(str_replace('/chat/completions', '', $api['url']), '/');
        $options['model'] = $options['model'] ?? $api['model'];

        $response_data = $this->execute_provider_request($provider, $messages, $options, $config, $slug);
        $parsed = $response_data['parsed'];
        $usage = $parsed['usage'];
        $elapsed_ms = $response_data['elapsed_ms'];

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

    /**
     * Handle standard provider call (key rotation + circuit breaker).
     */
    private function call_standard_provider(string $slug, array $messages, array $options, array $config) : array {
        $provider = $this->factory->make($slug);
        if (!$provider) {
            throw new \RuntimeException("Unknown provider: {$slug}");
        }

        $user_id = isset($options['user_id']) ? (int) $options['user_id'] : get_current_user_id();

        // Resolve model
        $this->resolve_provider_model($slug, $options, $provider);
        // Resolve api_base
        $this->resolve_api_base($slug, $config);
        // Resolve API keys (with rotation)
        $picked = $this->resolve_api_keys($slug, $config);
        $config['api_key'] = $picked['key'];

        $model = $options['model'] ?? ($config['model'] ?? '');

        $response_data = $this->execute_provider_request($provider, $messages, $options, $config, $slug);
        $elapsed_ms = $response_data['elapsed_ms'];

        // Handle transport error
        if ($response_data['error'] !== null) {
            $this->log_usage([
                'user_id'     => $user_id,
                'module'      => $options['module'] ?? 'general',
                'provider'    => $slug,
                'model'       => $model,
                'status'      => 'error',
                'error_code'  => $response_data['error']['code'],
                'elapsed_ms'  => $elapsed_ms,
            ]);
            throw new \RuntimeException($response_data['error']['message']);
        }

        $code = $response_data['http_code'];
        $body = $response_data['body'];

        // Handle HTTP error (4xx/5xx)
        if ($code >= 400) {
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
     * Resolve provider model from saved options or provider defaults.
     */
    private function resolve_provider_model(string $slug, array &$options, $provider) : void {
        if (empty($options['model'])) {
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            if (!empty($saved_models[$slug])) {
                $options['model'] = $saved_models[$slug];
            }
        }
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
    }

    /**
     * Resolve API base URL from saved options.
     */
    private function resolve_api_base(string $slug, array &$config) : void {
        if (empty($config['api_base'])) {
            $saved_bases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
            if (!empty($saved_bases[$slug])) {
                $config['api_base'] = $saved_bases[$slug];
            }
        }
    }

    /**
     * Resolve API keys: from config, saved options, or built-in defaults.
     * Supports multi-key rotation via KeyRotator.
     *
     * @return array{key: string, index: int, degraded: bool}
     */
    private function resolve_api_keys(string $slug, array &$config) : array {
        $raw_keys = !empty($config['api_keys']) ? (array) $config['api_keys'] : (isset($config['api_key']) ? [$config['api_key']] : []);
        if (empty($raw_keys)) {
            $saved_keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            if (!empty($saved_keys[$slug])) {
                $raw_keys = array_filter(array_map('trim', explode("\n", $saved_keys[$slug])));
            }
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
        return $picked;
    }

    /**
     * Execute HTTP request to provider and return standardized response data.
     *
     * @param object $provider  Provider instance
     * @param array  $messages  Chat messages
     * @param array  $options   Request options
     * @param array  $config    Provider config
     * @param string $slug      Provider slug
     * @return array{parsed: array|null, elapsed_ms: int, http_code: int, body: array|null, error: array|null}
     */
    private function execute_provider_request($provider, array $messages, array $options, array $config, string $slug) : array {
        $url = $provider->build_api_url('chat', $config);
        $headers = $provider->get_api_headers($config);
        $headers['Accept'] = 'application/json';
        $payload = $provider->format_chat_payload($messages, $options, $config);

        $started = microtime(true);
        $request_timeout = isset($options['timeout']) ? (int) $options['timeout'] : $provider->default_timeout();
        $response = SafeRemote::post($url, [
            'timeout'     => $request_timeout,
            'headers'     => $headers,
            'body'        => wp_json_encode($payload),
            'data_format' => 'body',
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        $elapsed_ms = (int) ((microtime(true) - $started) * 1000);

        if (is_wp_error($response)) {
            $err_code = $response->get_error_code();
            $err_msg  = $response->get_error_message();
            return [
                'parsed'     => null,
                'elapsed_ms' => $elapsed_ms,
                'http_code'  => 0,
                'body'       => null,
                'error'      => [
                    'code'    => is_string($err_code) ? $err_code : 'http_error',
                    'message' => 'HTTP error: ' . $err_msg,
                ],
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);
        $parsed = ($code < 400) ? $provider->parse_chat_response($body, $config) : null;

        return [
            'parsed'     => $parsed,
            'elapsed_ms' => $elapsed_ms,
            'http_code'  => $code,
            'body'       => $body,
            'error'      => null,
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
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (user_id, session_id, module, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_usd, status, error_code) VALUES (%d, %s, %s, %s, %s, %d, %d, %d, %f, %s, %s)",
            $row['user_id'], $row['session_id'], $row['module'], $row['provider'], $row['model'], $row['prompt_tokens'], $row['completion_tokens'], $row['total_tokens'], $cost, $row['status'], $row['error_code']
        ));
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
    private function estimate_cost_usd(string $provider, string $model, int $prompt, int $completion) : mixed     {
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
    private function is_circuit_open(string $slug): bool
    {
        return (int) get_transient('linked3_pcb_' . $slug) >= self::CIRCUIT_THRESHOLD;
    }

    /**
     * @param string $slug
     * @return void
     */
    private function reset_circuit(string $slug)
    : void {
        delete_transient('linked3_pcb_' . $slug);
    }

    /**
     * @param string $slug
     * @param string $message
     * @return void
     */
    private function record_failure(string $slug, string $message)
    : void {
        $key = 'linked3_pcb_' . $slug;
        // Read-modify-write race is acceptable here: transient TTL is short,
        // and a stale read at worst delays the circuit opening by one cycle.
        $n = (int) get_transient($key) + 1;
        set_transient($key, $n, 5 * MINUTE_IN_SECONDS);
    }
}
