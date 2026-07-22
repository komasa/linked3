<?php

declare(strict_types=1);
/**
 * Provider Strategy Interface.
 *
 * Every AI provider (OpenAI/Anthropic/Gemini/DeepSeek/Qwen/GLM/Kimi/豆包/混元/custom)
 * implements this interface so the AI Dispatcher can swap providers without the
 * caller caring about wire format. Mirrors aipower's ProviderStrategyInterface.
 *
 * 9 methods cover the full chat + embedding + streaming surface.
 *
 * @package Linked3
 * @subpackage Classes\Core\Providers
 */

namespace Linked3\Classes\Core\Providers;

if (!defined('ABSPATH')) {
    exit;
}

interface ProviderStrategyInterface
{
    /**
     * @return string Provider slug, e.g. 'openai', 'deepseek'.
     */
    public function slug(): string ;

    /**
     * Build the API endpoint URL for the given operation.
     *
     * @param string $operation 'chat' | 'stream' | 'embed' | 'models'
     * @param array  $config    Provider config (api_base, model, etc).
     * @return string
     */
    public function build_api_url(string $operation, array $config): string ;

    /**
     * @param array $config Provider config (includes api_key).
     * @return array<string,string> HTTP headers.
     */
    public function get_api_headers(array $config): array ;

    /**
     * Format the chat completion request payload.
     *
     * @param array $messages [{role, content}, ...]
     * @param array $options  model, temperature, max_tokens, top_p, stop, etc.
     * @param array $config   Provider config.
     * @return array
     */
    public function format_chat_payload(array $messages, array $options, array $config): array ;

    /**
     * Parse a (non-streaming) chat response body.
     *
     * @param mixed $body    Decoded response body.
     * @param array $config
     * @return array{content:string, usage:array, raw:array}
     */
    public function parse_chat_response($body, array $config);

    /**
     * Parse an error response body into a normalized WP_Error-like array.
     *
     * @param mixed $body
     * @param int   $status_code
     * @return array{code:string, message:string, status:int}
     */
    public function parse_error_response($body, int $status_code);

    /**
     * @param array $config
     * @return array<int,string> List of model IDs.
     */
    public function get_models(array $config): array ;

    /**
     * Build an embeddings request payload.
     *
     * @param string|array $input  Text or array of texts.
     * @param array        $options model, dimensions, etc.
     * @param array        $config
     * @return array
     */
    public function format_embed_payload(string|array $input, array $options, array $config): array ;

    /**
     * Parse an embeddings response body.
     *
     * @param mixed $body
     * @param array $config
     * @return array<int,float[]> Vectors.
     */
    public function parse_embed_response($body, array $config): array ;
}
