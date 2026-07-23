<?php

declare(strict_types=1);
/**
 * OpenAI-compatible provider.
 *
 * This single concrete strategy covers every OpenAI-compatible endpoint:
 *   - OpenAI itself (api.openai.com)
 *   - DeepSeek (api.deepseek.com)
 *   - Kimi/Moonshot (api.moonshot.cn)
 *   - Qwen-Plus / DashScope OpenAI-compat mode (dashscope.aliyuncs.com/compatible-mode)
 *   - 豆包 Volces Ark OpenAI-compat (ark.cn-beijing.volces.com/api/v3)
 *   - Custom user-defined OpenAI-compatible endpoints
 *
 * Each is just a different api_base + model list. The wire format is identical.
 *
 * This file represents v0.1.5: the first migrated provider. v0.1.6 will add
 * the multi-key rotator; v0.1.7 the Factory.
 *
 * @package Linked3
 * @subpackage Classes\Core\Providers
 */

namespace Linked3\Classes\Core\Providers;

if (!defined('ABSPATH')) {
    exit;
}

final class OpenAICompatProvider extends BaseProviderStrategy
{
    /** @var string */
    private $slug;

    /** @var string */
    private $default_base;

    /**
     * @param string $slug         e.g. 'openai', 'deepseek', 'kimi', 'qwen', 'doubao'
     * @param string $default_base Default API base URL if config omits api_base.
     */
    public function __construct($slug, $default_base) {
        $this->slug = $slug;
        $this->default_base = $default_base;
    }

    public function slug() : string {
        return $this->slug;
    }

    protected function default_api_base() : string     {
        return $this->default_base;
    }

    public function build_api_url(string $operation, array $config) : string {
        $base = $this->api_base($config);
        switch ($operation) {
            case 'stream':
            case 'chat':
                return $base . '/chat/completions';
            case 'embed':
                return $base . '/embeddings';
            case 'models':
                return $base . '/models';
            default:
                return $base;
        }
    }

    public function get_models(array $config) : array     {
        // Static fallback model lists per provider; real list fetched live
        // via the 'models' operation when admin tests connection (v0.1.10).
        $fallbacks = [
            'openai'   => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
            'deepseek' => ['deepseek-chat', 'deepseek-reasoner'],
            'kimi'     => ['moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k'],
            'qwen'     => ['qwen-plus', 'qwen-max', 'qwen-turbo'],
            'doubao'   => ['doubao-pro-4k', 'doubao-pro-32k', 'doubao-lite-4k'],
        ];
        return $fallbacks[$this->slug] ?? ['custom'];
    }
}
