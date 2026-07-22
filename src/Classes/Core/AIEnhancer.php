<?php

declare(strict_types=1);
/**
 * AI 内容增强器 — 原版隐藏功能集合。
 *
 * 迁移原版 v2.9.6 的:
 *   - require_html: AI 返回 HTML 格式(不用 Markdown)
 *   - require_tag: AI 自动生成文章标签
 *   - enable_ai_summary: AI 自动生成搜索引擎精选摘要
 *   - identifier_suffix: AI 内容免责声明后缀
 *   - auto_select_category: 根据内容自动选择分类
 *   - generate_seo_title: 从内容提取关键词生成 SEO 标题
 *   - time_window: 时间段限制(只在 9:00-18:00 运行)
 *
 * @package Linked3
 * @subpackage Classes\Core
 */

namespace Linked3\Classes\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class AIEnhancer
{
    /**
     * 根据高级设置,在 AI prompt 里追加格式要求。
     *
     * @param string $prompt 原始 prompt
     * @param array $settings {require_html, require_tag, enable_ai_summary}
     * @return string
     */
    public function apply_format_requirements(string $prompt, array $settings = []) : mixed {
        $settings = wp_parse_args($settings, $this->default_settings());

        if (!empty($settings['require_html'])) {
            $prompt .= "\n返回的文章内容必须用 HTML 标签格式,不要加 CSS 代码,不需要 <!DOCTYPE html>、<html>、<head>、<body> 标签。文章标题用 H1 标签。";
        }

        if (!empty($settings['enable_ai_summary'])) {
            $prompt .= " 请在文章尾部嵌入一段适配搜索引擎精选摘要,格式为:摘要:xxx。\n";
        }

        if (!empty($settings['require_tag'])) {
            $prompt .= "\n请在文章尾部加入适当的文章 tag 标签,标签格式必须为:{1、标签1}{2、标签2}。";
        }

        return $prompt;
    }

    /**
     * 追加 AI 标识符后缀。
     *
     * @param string $content
     * @return string
     */
    public function append_identifier_suffix(string $content) : mixed     {
        $enabled = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_enabled', 0);
        if (!$enabled) return $content;

        $suffix = get_option(LINKED3_OPTION_PREFIX . 'ai_suffix_text', '');
        if (empty($suffix)) {
            $suffix = '，基于公开技术资料和厂商官方信息整合撰写，以确保信息的时效性与客观性。我们建议您将所有信息作为决策参考，并最终以各云厂商官方页面的最新公告为准。';
        }

        return $content . "\n\n---\n" . $suffix;
    }

    /**
     * 检查当前时间是否在允许的时间段内。
     *
     * @param array $settings {time_window_enabled, time_window_start, time_window_end}
     * @return bool
     */
    public function is_within_time_window(array $settings = []): bool
    {
        if (empty($settings['time_window_enabled'])) {
            return true; // 未启用时间段限制
        }
        $start = $settings['time_window_start'] ?? '09:00';
        $end = $settings['time_window_end'] ?? '18:00';
        $now = current_time('H:i');
        return ($now >= $start && $now <= $end);
    }

    /**
     * 默认高级设置。
     */
    public function default_settings(): array {
        return [
            'require_html' => false,
            'require_tag' => false,
            'enable_ai_summary' => false,
            'time_window_enabled' => false,
            'time_window_start' => '09:00',
            'time_window_end' => '18:00',
        ];
    }

    /**
     * 获取当前高级设置 (从 option 读)。
     */
    public function get_settings()
    {
        return wp_parse_args(
            (array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []),
            $this->default_settings()
        );
    }

    /**
     * 保存高级设置。
     */
    public function save_settings(array $input)
    {
        $clean = [
            'require_html' => !empty($input['require_html']),
            'require_tag' => !empty($input['require_tag']),
            'enable_ai_summary' => !empty($input['enable_ai_summary']),
            'time_window_enabled' => !empty($input['time_window_enabled']),
            'time_window_start' => sanitize_text_field($input['time_window_start'] ?? '09:00'),
            'time_window_end' => sanitize_text_field($input['time_window_end'] ?? '18:00'),
        ];
        update_option(LINKED3_OPTION_PREFIX . 'advanced_settings', $clean);
        return $clean;
    }
}
