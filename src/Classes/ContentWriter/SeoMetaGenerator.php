<?php

declare(strict_types=1);
/**
 * SEO 元数据生成器 — v3.1.0
 *
 * 统一 SEO 元数据生成逻辑,供 Content Writer UI + AutoGPT 共用。
 *
 * 5 类元数据:
 *   - title:           SEO 友好标题
 *   - meta:            Meta Description (150-160 字)
 *   - keyword:         焦点关键词 + 长尾词
 *   - excerpt:         文章摘要 (100 字)
 *   - tags:            标签 (5-8 个)
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter
 */

namespace Linked3\Classes\ContentWriter;

use Linked3\Classes\Core\AIDispatcher;



if (!defined('ABSPATH')) {
    exit;
}
final class SeoMetaGenerator
{
    /**
     * 生成全部 5 类 SEO 元数据。
     *
     * @param array $args {
     *   title:        string 原标题 (可选)
     *   topic:        string 主题/关键词
     *   keywords:     string 关键词
     *   content:      string 正文
     *   template_config: array 模板配置 (含 6 个 custom_*_prompt)
     *   provider:     string AI provider
     *   model:        string AI model
     *   user_id:      int 用户 ID (用于计费)
     *   gen_title:    bool 是否生成标题
     *   gen_meta:     bool 是否生成 meta
     *   gen_keyword:  bool 是否生成焦点关键词
     *   gen_excerpt:  bool 是否生成摘要
     *   gen_tags:     bool 是否生成标签
     * }
     * @return array {title, seo_meta, focus_keyword, excerpt, tags}
     */
    public static function generate_all(array $args) : array {
        $title = $args['title'] ?? '';
        $topic = $args['topic'] ?? '';
        $keywords = $args['keywords'] ?? '';
        $content = $args['content'] ?? '';
        $cfg = $args['template_config'] ?? [];
        $provider = $args['provider'] ?? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model = $args['model'] ?? ($saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct');
        $user_id = $args['user_id'] ?? get_current_user_id();

        $gen_title = !empty($args['gen_title']);
        $gen_meta = !empty($args['gen_meta']);
        $gen_keyword = !empty($args['gen_keyword']);
        $gen_excerpt = !empty($args['gen_excerpt']);
        $gen_tags = !empty($args['gen_tags']);

        $result = [
            'title' => '',
            'seo_meta' => '',
            'focus_keyword' => '',
            'excerpt' => '',
            'tags' => '',
        ];

        $dispatcher = AIDispatcher::instance();

        // 1. 生成标题
        if ($gen_title && empty($title)) {
            try {
                $prompt = !empty($cfg['custom_title_prompt'])
                    ? str_replace(['{topic}', '{keywords}'], [$topic, $keywords], $cfg['custom_title_prompt'])
                    : '为以下主题生成一个 SEO 友好的中文标题(8-15字),只返回标题文本,不要其他内容。' . "\n\n主题:{$topic}\n关键词:{$keywords}";
                // v19.41: 绞杀模式 — system_prompt 可被元提示词杠杆增强
                $seo_system = apply_filters('linked3_seo_system_prompt', '你是专业的SEO内容优化专家。', ['task' => 'title', 'topic' => $topic]);
                $r = $dispatcher->chat(
                    [['role' => 'system', 'content' => $seo_system], ['role' => 'user', 'content' => $prompt]],
                    ['provider' => $provider, 'model' => $model, 'temperature' => 0.5, 'max_tokens' => 80, 'module' => 'content_writer', 'user_id' => $user_id],
                    ['fallback_providers' => []]
                );
                $result['title'] = trim(wp_strip_all_tags($r['content']), "\"'“”‘’");
            } catch (\Throwable $e) { /* 静默失败 */ }
        }

        // 2. 生成 Meta Description
        if ($gen_meta) {
            try {
                $prompt = !empty($cfg['custom_meta_prompt'])
                    ? str_replace(['{title}', '{keywords}', '{topic}'], [$title ?: $topic, $keywords, $topic], $cfg['custom_meta_prompt'])
                    : '为以下文章生成 150-160 字的 SEO meta description,包含主关键词,只返回描述文本。' . "\n\n标题:" . ($title ?: $topic) . "\n关键词:{$keywords}\n\n正文摘要:" . mb_substr(wp_strip_all_tags($content), 0, 500);
                $r = $dispatcher->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 200, 'module' => 'content_writer', 'user_id' => $user_id],
                    ['fallback_providers' => []]
                );
                $result['seo_meta'] = trim(wp_strip_all_tags($r['content']));
            } catch (\Throwable $e) { /* 静默失败 */ }
        }

        // 3. 提取焦点关键词
        if ($gen_keyword) {
            try {
                $prompt = !empty($cfg['custom_keyword_prompt'])
                    ? str_replace(['{title}', '{topic}'], [$title ?: $topic, $topic], $cfg['custom_keyword_prompt'])
                    : '从以下文章中提取 1 个焦点关键词和 5 个长尾关键词,用逗号分隔,只返回关键词。' . "\n\n标题:" . ($title ?: $topic) . "\n\n正文:" . mb_substr(wp_strip_all_tags($content), 0, 800);
                $r = $dispatcher->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 150, 'module' => 'content_writer', 'user_id' => $user_id],
                    ['fallback_providers' => []]
                );
                $result['focus_keyword'] = trim(wp_strip_all_tags($r['content']));
            } catch (\Throwable $e) { /* 静默失败 */ }
        }

        // 4. 生成摘要
        if ($gen_excerpt) {
            try {
                $prompt = !empty($cfg['custom_excerpt_prompt'])
                    ? str_replace(['{title}', '{topic}'], [$title ?: $topic, $topic], $cfg['custom_excerpt_prompt'])
                    : '为以下文章生成 100 字以内的摘要,只返回摘要文本。' . "\n\n标题:" . ($title ?: $topic) . "\n\n正文:" . mb_substr(wp_strip_all_tags($content), 0, 800);
                $r = $dispatcher->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 150, 'module' => 'content_writer', 'user_id' => $user_id],
                    ['fallback_providers' => []]
                );
                $result['excerpt'] = trim(wp_strip_all_tags($r['content']));
            } catch (\Throwable $e) { /* 静默失败 */ }
        }

        // 5. 生成标签
        if ($gen_tags) {
            try {
                $prompt = !empty($cfg['custom_tags_prompt'])
                    ? str_replace(['{title}', '{keywords}', '{topic}'], [$title ?: $topic, $keywords, $topic], $cfg['custom_tags_prompt'])
                    : '为以下文章生成 5-8 个标签,用逗号分隔,只返回标签。' . "\n\n标题:" . ($title ?: $topic) . "\n关键词:{$keywords}";
                $r = $dispatcher->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'max_tokens' => 100, 'module' => 'content_writer', 'user_id' => $user_id],
                    ['fallback_providers' => []]
                );
                $result['tags'] = trim(wp_strip_all_tags($r['content']));
            } catch (\Throwable $e) { /* 静默失败 */ }
        }

        return $result;
    }

    /**
     * 把生成的元数据保存到 post meta (兼容 Yoast/RankMath/AIOSEO)。
     */
    public static function save_to_post($post_id, array $meta)
    : void {
        if (!$post_id) return;

        if (!empty($meta['seo_meta'])) {
            update_post_meta($post_id, '_aioseo_description', $meta['seo_meta']);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta['seo_meta']);
            update_post_meta($post_id, 'linked3_seo_meta', $meta['seo_meta']);
        }
        if (!empty($meta['focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $meta['focus_keyword']);
            update_post_meta($post_id, 'rank_math_focus_keyword', $meta['focus_keyword']);
            update_post_meta($post_id, 'linked3_focus_keyword', $meta['focus_keyword']);
        }
        if (!empty($meta['excerpt'])) {
            wp_update_post(['ID' => $post_id, 'post_excerpt' => $meta['excerpt']]);
        }
        if (!empty($meta['tags'])) {
            $tag_arr = array_filter(array_map('trim', explode(',', $meta['tags'])));
            if (!empty($tag_arr)) {
                wp_set_post_tags($post_id, $tag_arr, false);
            }
        }
    }
}
