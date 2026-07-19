<?php

declare(strict_types=1);
/**
 * Writing Center Extensions — fills feature gaps.
 *
 * G5.5: Adds quality scoring, rewrite, auto-image, internal links
 * to the Writing Center.
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter
 * @since      27.8.0
 */

namespace Linked3\Classes\ContentWriter;

use Linked3\Classes\Core\AIDispatcher;

if (!defined('ABSPATH')) exit;

final class WritingExtensions
{
    /**
     * AI quality scoring — deep evaluation beyond word count.
     */
    public static function ajax_quality_score(): void
    {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Forbidden'], 403);
        check_ajax_referer('linked3_content_writer', 'nonce');

        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        if (empty($content)) wp_send_json_error(['message' => '内容为空'], 400);

        $prompt = "请对以下文章进行质量评分(0-100), 从5个维度各打分:\n"
            . "1. 结构清晰度 (0-20)\n2. 内容深度 (0-20)\n3. SEO友好度 (0-20)\n"
            . "4. 可读性 (0-20)\n5. 原创性 (0-20)\n\n"
            . "文章内容:\n" . mb_substr($content, 0, 2000) . "\n\n"
            . "输出格式: 每维度一行, 最后总分。附1-2句改进建议。";

        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['temperature' => 0.3, 'max_tokens' => 300, 'module' => 'quality_score']
            );
            wp_send_json_success(['evaluation' => $result['content'] ?? '评分失败']);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Rewrite/polish existing content.
     */
    public static function ajax_rewrite(): void
    {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Forbidden'], 403);
        check_ajax_referer('linked3_content_writer', 'nonce');

        $content = wp_unslash($_POST['content'] ?? '');
        $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'polish'));
        if (empty($content)) wp_send_json_error(['message' => '内容为空'], 400);

        $prompts = [
            'polish'  => '请润色以下文章, 保持原意, 提升表达质量和可读性:',
            'expand'  => '请扩写以下文章, 增加细节和论据, 保持原结构:',
            'simplify'=> '请简化以下文章, 用更短的句子和更简单的词汇, 保持核心信息:',
            'rewrite' => '请重写以下文章, 用完全不同的表达方式, 但保持相同的信息:',
        ];
        $prompt = ($prompts[$mode] ?? $prompts['polish']) . "\n\n" . $content;

        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['temperature' => 0.7, 'max_tokens' => 3000, 'module' => 'rewrite']
            );
            wp_send_json_success(['content' => $result['content'] ?? '']);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Auto-suggest internal links for the content.
     */
    public static function ajax_suggest_internal_links(): void
    {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'Forbidden'], 403);
        check_ajax_referer('linked3_content_writer', 'nonce');

        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        $topic = sanitize_text_field(wp_unslash($_POST['topic'] ?? ''));

        // Find existing posts that match the topic
        global $wpdb;
        $keywords = array_filter(explode(' ', $topic));
        $suggestions = [];

        foreach ($keywords as $kw) {
            if (mb_strlen($kw) < 2) continue;
            $posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts} 
                 WHERE post_type = 'post' AND post_status = 'publish' 
                 AND post_title LIKE %s LIMIT 5",
                '%' . $wpdb->esc_like($kw) . '%'
            ));
            foreach ($posts as $p) {
                $suggestions[] = [
                    'id' => $p->ID,
                    'title' => $p->post_title,
                    'url' => get_permalink($p->ID),
                ];
            }
        }

        wp_send_json_success(['links' => array_slice($suggestions, 0, 10)]);
    }

    /**
     * Register AJAX endpoints.
     */
    public static function register(): void
    {
        add_action('wp_ajax_linked3_quality_score', [self::class, 'ajax_quality_score']);
        add_action('wp_ajax_linked3_rewrite', [self::class, 'ajax_rewrite']);
        add_action('wp_ajax_linked3_suggest_internal_links', [self::class, 'ajax_suggest_internal_links']);
    }
}

add_action('init', [WritingExtensions::class, 'register'], 20);
