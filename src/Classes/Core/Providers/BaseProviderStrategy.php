<?php

declare(strict_types=1);
/**
 * Base Provider Strategy — shared utilities for every concrete provider.
 *
 * Concrete providers extend this and only override the methods where their
 * wire format differs from the OpenAI-compatible default (which covers
 * DeepSeek, Kimi/Moonshot, Qwen-Plus, 豆包, and any custom OpenAI-compatible endpoint).
 *
 * Shared concerns handled here:
 *   - API key retrieval + redaction for logs
 *   - Normalised error extraction (OpenAI / Anthropic / generic shapes)
 *   - Default headers (JSON + auth bearer)
 *   - Token usage extraction (handles usage / token_usage / prompt_tokens shapes)
 *   - Retry budget + timeout defaults
 *
 * @package Linked3
 * @subpackage Classes\Core\Providers
 */

namespace Linked3\Classes\Core\Providers;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseProviderStrategy implements ProviderStrategyInterface
{
    /** @var int Default per-request timeout (seconds). v5.2.9: 60→120 for long-form content. */
    protected $default_timeout = 120;

    /** @var int Default max retries. */
    protected $default_retries = 2;

    /**
     * @param array $config
     * @return string
     */
    protected function api_key(array $config) : string {
        $key = isset($config['api_key']) ? (string) $config['api_key'] : '';
        return $key;
    }

    /**
     * @param array $config
     * @return string
     */
    protected function api_base(array $config) : string {
        $base = isset($config['api_base']) ? rtrim((string) $config['api_base'], '/') : '';
        if ($base === '') {
            $base = $this->default_api_base();
        }
        return $base;
    }

    /**
     * @return string
     */
    abstract protected function default_api_base();

    /**
     * Default OpenAI-compatible headers. Override for Anthropic (x-api-key)
     * or Google (x-goog-api-key) style auth.
     *
     * @param array $config
     * @return array<string,string>
     */
    public function get_api_headers(array $config) : array {
        $headers = [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
        $key = $this->api_key($config);
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer ' . $key;
        }
        return $headers;
    }

    /**
     * Default OpenAI-compatible chat payload. Override for non-compliant providers.
     *
     * @param array $messages
     * @param array $options
     * @param array $config
     * @return array
     */
    public function format_chat_payload(array $messages, array $options, array $config) : array {
        $payload = [
            'model'       => isset($options['model']) ? $options['model'] : ($config['model'] ?? ''),
            'messages'    => $messages,
            'temperature' => isset($options['temperature']) ? (float) $options['temperature'] : 0.7,
        ];
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }
        if (isset($options['top_p'])) {
            $payload['top_p'] = (float) $options['top_p'];
        }
        if (!empty($options['stop'])) {
            $payload['stop'] = $options['stop'];
        }
        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }
        return $payload;
    }

    /**
     * Default OpenAI-compatible response parser.
     *
     * @param mixed $body
     * @param array $config
     * @return array{content:string, usage:array, raw:array}
     */
    public function parse_chat_response($body, array $config) {
        $body = is_array($body) ? $body : [];
        $content = '';
        if (isset($body['choices'][0]['message']['content'])) {
            $content = (string) $body['choices'][0]['message']['content'];
        }
        $usage = $this->extract_usage($body);
        return ['content' => $content, 'usage' => $usage, 'raw' => $body];
    }

    /**
     * Default OpenAI-compatible embeddings payload.
     *
     * @param string|array $input
     * @param array        $options
     * @param array        $config
     * @return array
     */
    public function format_embed_payload(string|array $input, array $options, array $config): array
    {
        return [
            'model' => isset($options['model']) ? $options['model'] : ($config['embed_model'] ?? 'text-embedding-3-small'),
            'input' => $input,
        ];
    }

    /**
     * Default OpenAI-compatible embeddings response parser.
     *
     * @param mixed $body
     * @param array $config
     * @return array<int,float[]>
     */
    public function parse_embed_response($body, array $config): array
    {
        $body = is_array($body) ? $body : [];
        $out = [];
        if (isset($body['data']) && is_array($body['data'])) {
            foreach ($body['data'] as $item) {
                if (isset($item['embedding']) && is_array($item['embedding'])) {
                    $out[] = array_map('floatval', $item['embedding']);
                }
            }
        }
        return $out;
    }

    /**
     * Normalised error extractor — handles OpenAI / Anthropic / generic shapes.
     *
     * @param mixed $body
     * @param int   $status_code
     * @return array{code:string, message:string, status:int}
     */
    public function parse_error_response($body, int $status_code) {
        $body = is_array($body) ? $body : [];
        $status_code = (int) $status_code;
        $code = 'linked3_provider_error';
        $message = __('Unknown provider error.', 'linked3');

        if (isset($body['error']['code'])) {
            $code = (string) $body['error']['code'];
        } elseif (isset($body['error']['type'])) {
            $code = (string) $body['error']['type'];
        } elseif (isset($body['error']['message']) && is_string($body['error']['message'])) {
            $code = 'provider_' . $status_code;
        }

        if (isset($body['error']['message']) && is_string($body['error']['message'])) {
            $message = (string) $body['error']['message'];
        } elseif (isset($body['message'])) {
            $message = (string) $body['message'];
        } elseif (isset($body['error']) && is_string($body['error'])) {
            $message = (string) $body['error'];
        }

        return ['code' => $code, 'message' => $message, 'status' => $status_code];
    }

    /**
     * Token usage extractor — handles OpenAI (usage) / Anthropic (usage) /
     * some Chinese providers (token_usage) shapes.
     *
     * @param array $body
     * @return array{prompt_tokens:int, completion_tokens:int, total_tokens:int}
     */
    protected function extract_usage(array $body): array {
        $u = $body['usage'] ?? ($body['token_usage'] ?? []);
        return [
            'prompt_tokens'     => isset($u['prompt_tokens']) ? (int) $u['prompt_tokens'] : 0,
            'completion_tokens' => isset($u['completion_tokens']) ? (int) $u['completion_tokens'] : (isset($u['completion_token']) ? (int) $u['completion_token'] : 0),
            'total_tokens'      => isset($u['total_tokens']) ? (int) $u['total_tokens'] : (isset($u['total_token']) ? (int) $u['total_token'] : 0),
        ];
    }

    /**
     * @return int
     */
    public function default_timeout(): int
    {
        return (int) apply_filters('linked3/provider_timeout', $this->default_timeout, $this->slug());
    }

}
