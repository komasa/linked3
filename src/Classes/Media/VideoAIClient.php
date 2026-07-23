<?php

declare(strict_types=1);
/**
 * 视频 AI 调用封装 — 从 VideoGenerator 拆分 (v5.4.0, DRY Keystone)。
 *
 * 消除原 VideoGenerator 中 6 处重复的:
 *   - Provider/Model 解析 (3 行 × 6 = 18 行)
 *   - try/catch + AIDispatcher::instance()->chat() 调用 (12 行 × 6 = 72 行)
 *   - fallback_providers 配置 (1 行 × 6 = 6 行)
 *   - module='video' 标记 (1 行 × 6 = 6 行)
 *
 * 统一封装后, VideoGenerator 的 5 个 generate_* 方法只需构造
 * messages + opts, 调用 $this->ai_client->chat() 即可。
 *
 * @package Linked3
 * @subpackage Classes\Media
 * @since 27.4.1
 */

namespace Linked3\Classes\Media;

use Linked3\Classes\Core\AIDispatcher;

if (!defined('ABSPATH')) {
    exit;
}

final class VideoAIClient
{
    /**
     * 封装 AIDispatcher::chat() 调用 — 统一 try/catch + fallback + module=video。
     *
     * @param array $messages  Chat messages [{role, content}, ...]
     * @param array $opts {
     *     @type string $provider     指定 provider (默认读 option)
     *     @type int    $max_tokens    最大 token 数 (默认 2000)
     *     @type float  $temperature  温度 (默认 0.7)
     *     @type int    $user_id       用户 ID (默认当前用户)
     *     @type int    $time_limit    set_time_limit 值, 0 = 不设置
     * }
     * @return array{content:string, usage:array, provider:string, model:string}|\WP_Error
     */
    public function chat(array $messages, array $opts = []): mixed
    {
        [$provider, $model] = $this->resolve_provider($opts);

        // 动态超时 (v5.3.2)
        if (!empty($opts['time_limit']) && function_exists('set_time_limit')) {
            @set_time_limit((int) $opts['time_limit']);
        }

        try {
            $result = AIDispatcher::instance()->chat(
                $messages,
                [
                    'provider'    => $provider,
                    'model'       => $model,
                    'temperature' => $opts['temperature'] ?? 0.7,
                    'max_tokens'  => $opts['max_tokens'] ?? 2000,
                    'module'      => 'video',
                    'user_id'     => $opts['user_id'] ?? get_current_user_id(),
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );

            // 确保返回结构一致
            $result['provider'] = $result['provider'] ?? $provider;
            $result['model']    = $result['model']    ?? $model;
            return $result;
        } catch (\Throwable $e) {
            return new \WP_Error(
                'ai_failed',
                'AI 调用失败: ' . $e->getMessage()
            );
        }
    }

    /**
     * 解析 provider + model (提取自 6 处重复代码)。
     *
     * @param array $opts
     * @return array [provider, model]
     */
    private function resolve_provider(array $opts): array
    {
        $provider = $opts['provider']
            ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');

        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        return [$provider, $model];
    }
}
