<?php

declare(strict_types=1);
/**
 * AIDispatcherChatTrait — chat() + call_single() provider-chain logic.
 *
 * @package Linked3\Core
 */

namespace Linked3\Classes\Core\Traits;

if (!defined('ABSPATH')) exit;

trait AIDispatcherChatTrait
{
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
        $user_id = isset($options['user_id']) ? (int) $options['user_id'] : get_current_user_id();
        \Linked3\Classes\Security\RateLimiter::per_user_hourly($user_id, 'ai_chat');

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

        $default_provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $provider_slug = $options['provider'] ?? $default_provider;
        if (strpos($provider_slug, 'custom_') === 0) {
            $custom_id = substr($provider_slug, 7);
            $custom_apis = (array) get_option(LINKED3_OPTION_PREFIX . 'custom_apis', []);
            if (isset($custom_apis[$custom_id])) {
                $config['custom_api'] = $custom_apis[$custom_id];
            }
        }
        $fallbacks = $config['fallback_providers'] ?? [];
        $bypass_circuit = !empty($config['force_bypass_circuit']);

        $chain = array_merge([$provider_slug], $fallbacks);

        $last_error = null;
        foreach ($chain as $slug) {
            if (!$bypass_circuit && $this->is_circuit_open($slug)) {
                $this->log->warning('ai', "Provider {$slug} circuit open, skipping", ['chain' => $chain]);
                continue;
            }
            try {
                $result = $this->call_single($slug, $messages, $options, $config);
                $this->reset_circuit($slug);
                if ($this->tokens && !empty($result['usage']['total_tokens'])) {
                    $bot_id = isset($options['bot_id']) ? (int) $options['bot_id'] : 0;
                    $this->tokens->record($user_id, $session_id, (int) $result['usage']['total_tokens'], $bot_id);
                }
                return $result;
            } catch (\Throwable $e) {
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
    private function call_single(string $slug, array $messages, array $options, array $config): array {
        // 自定义 API 站点支持: slug 格式 custom_xxx
        if (strpos($slug, 'custom_') === 0) {
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

            $url = $provider->build_api_url('chat', $config);
            $headers = $provider->get_api_headers($config);
            $headers['Accept'] = 'application/json';
            $payload = $provider->format_chat_payload($messages, $options, $config);

            $started = microtime(true);
            $custom_timeout = isset($options['timeout']) ? (int) $options['timeout'] : $provider->default_timeout();
            $response = \Linked3\Includes\Http\SafeRemote::post($url, [
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

        $user_id = isset($options['user_id']) ? (int) $options['user_id'] : get_current_user_id();

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
        if (empty($config['api_base'])) {
            $saved_bases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
            if (!empty($saved_bases[$slug])) {
                $config['api_base'] = $saved_bases[$slug];
            }
        }

        $raw_keys = !empty($config['api_keys']) ? (array) $config['api_keys'] : (isset($config['api_key']) ? [$config['api_key']] : []);
        if (empty($raw_keys)) {
            $saved_keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            if (!empty($saved_keys[$slug])) {
                $raw_keys = array_filter(array_map('trim', explode("\n", $saved_keys[$slug])));
            }
        }
        if (empty($raw_keys) && $slug === 'siliconflow') {

        }
        $keys = [];
        foreach ($raw_keys as $k) {
            $decrypted = \Linked3\Includes\Crypto::decrypt((string) $k);
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
        $request_timeout = isset($options['timeout']) ? (int) $options['timeout'] : $provider->default_timeout();
        $response = \Linked3\Includes\Http\SafeRemote::post($url, [
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
            $this->log_usage([
                'user_id'     => $user_id,
                'module'      => $options['module'] ?? 'general',
                'provider'    => $slug,
                'model'       => $model,
                'status'      => 'error',
                'error_code'  => is_string($err_code) ? $err_code : 'http_error',
                'elapsed_ms'  => $elapsed_ms,
            ]);
            throw new \RuntimeException('HTTP error: ' . $err_msg);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

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
}
