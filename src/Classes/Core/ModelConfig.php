<?php

declare(strict_types=1);
/**
 * Model Config — v19.53 统一 AI 模型配置.
 *
 * L06 元抽象：从 48 处硬编码模型名中提取统一配置。
 * 所有模块通过此工具获取默认模型，避免散落的硬编码。
 *
 * @package Linked3
 * @subpackage Classes\Core
 */

namespace Linked3\Classes\Core;

if (!defined('ABSPATH')) {
    exit;
}

class ModelConfig
{
    /**
     * 获取指定 provider 的默认聊天模型.
     *
     * @param string $provider Provider slug
     * @return string 模型名
     */
    public static function default_chat_model(string $provider = ''): string
    {
        // 优先从用户配置读取
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        if (!empty($provider) && !empty($saved_models[$provider])) {
            return $saved_models[$provider];
        }

        // 全局默认
        $global_default = get_option(LINKED3_OPTION_PREFIX . 'default_chat_model', '');
        if (!empty($global_default)) {
            return $global_default;
        }

        // Provider 专属默认
        $provider_defaults = [
            'siliconflow' => 'Qwen/Qwen2.5-7B-Instruct',
            'openai'      => 'gpt-4o-mini',
            'deepseek'    => 'deepseek-chat',
            'zhipu'       => 'glm-4-flash',
            'anthropic'   => 'claude-3-haiku-20240307',
        ];

        return $provider_defaults[$provider] ?? 'gpt-4o-mini';
    }

    /**
     * 获取默认图像模型.
     *
     * @return string
     */
    public static function default_image_model(): string
    {
        return get_option(LINKED3_OPTION_PREFIX . 'default_image_model', 'dall-e-3');
    }

    /**
     * 获取默认 provider.
     *
     * @return string
     */
    public static function default_provider(): string
    {
        return get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
    }
}
