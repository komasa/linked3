<?php

declare(strict_types=1);
/**
 * Meta Lever Hooks Registrar — v19.50.1 统一杠杆钩子注册器.
 *
 * 将所有模块的 system_prompt apply_filters 钩子统一连接到 MetaLever Registry。
 * 这样每个模块不需要自己写 add_filter，只需在生成器中 apply_filters 即可。
 *
 * 支持的钩子：
 *   - linked3_xhs_system_prompt        → 小红书生成器
 *   - linked3_seo_system_prompt        → SEO Meta 生成器
 *   - linked3_video_system_prompt      → 视频脚本生成器
 *   - linked3_comic_system_prompt      → 漫画分镜生成器
 *   - linked3_content_writer_system_prompt → 长文写作生成器
 *   - linked3_book_system_prompt       → 图书工厂生成器
 *
 * @package Linked3
 * @subpackage Classes\MetaLever
 */

namespace Linked3\Classes\MetaLever;

if (!defined('ABSPATH')) {
    exit;
}

class MetaLeverHooksRegistrar
{
    /**
     * 钩子 → 任务类型 映射表.
     */
    const HOOK_TASK_MAP = [
        'linked3_xhs_system_prompt'             => 'xhs_generate',
        'linked3_seo_system_prompt'             => 'seo_article',
        'linked3_video_system_prompt'           => 'video_script',
        'linked3_comic_system_prompt'           => 'comic_split',
        'linked3_content_writer_system_prompt'  => 'content_writer',
        'linked3_book_system_prompt'            => 'book_factory',
    ];

    /**
     * 注册所有钩子.
     */
    public static function register(): void
    {
        foreach (self::HOOK_TASK_MAP as $hook => $task) {
            add_filter($hook, [__CLASS__, 'enhance'], 10, 2);
        }
    }

    /**
     * 通用增强方法 — 所有钩子共用.
     *
     * @param string $base_prompt 基础 system_prompt
     * @param array  $params      上下文参数（可选）
     * @return string
     */
    public static function enhance(string $base_prompt, array $params = []) : mixed {
        // 获取当前钩子名（确定任务类型）
        $current_hook = current_filter();
        $task = self::HOOK_TASK_MAP[$current_hook] ?? 'default';

        // 如果 params 中指定了 task，优先使用
        if (is_array($params) && isset($params['task'])) {
            $task = $params['task'];
        }

        if (class_exists('\\Linked3\\Classes\\MetaLever\\MetaLeverRegistry')) {
            return MetaLeverRegistry::enhance_prompt($task, $base_prompt);
        }

        return $base_prompt;
    }
}
