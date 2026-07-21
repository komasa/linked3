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

        $ctx = [
            'title'    => $title,
            'topic'    => $topic,
            'keywords' => $keywords,
            'content'  => $content,
            'cfg'      => $cfg,
            'provider' => $provider,
            'model'    => $model,
            'user_id'  => $user_id,
        ];

        $dispatcher = AIDispatcher::instance();
        $result = [
            'title'         => '',
            'seo_meta'      => '',
            'focus_keyword' => '',
            'excerpt'       => '',
            'tags'          => '',
        ];

        // 1. Title (only if not already provided)
        if (!empty($args['gen_title']) && empty($title)) {
            $result['title'] = self::generate_single($dispatcher, $ctx, 'title', [
                'temperature' => 0.5,
                'max_tokens'  => 80,
                'system'      => apply_filters('linked3_seo_system_prompt', '你是专业的SEO内容优化专家。', ['task' => 'title', 'topic' => $topic]),
            ]);
        }

        // 2. Meta Description
        if (!empty($args['gen_meta'])) {
            $result['seo_meta'] = self::generate_single($dispatcher, $ctx, 'meta', [
                'temperature' => 0.3,
                'max_tokens'  => 200,
            ]);
        }

        // 3. Focus Keyword
        if (!empty($args['gen_keyword'])) {
            $result['focus_keyword'] = self::generate_single($dispatcher, $ctx, 'keyword', [
                'temperature' => 0.3,
                'max_tokens'  => 150,
            ]);
        }

        // 4. Excerpt
        if (!empty($args['gen_excerpt'])) {
            $result['excerpt'] = self::generate_single($dispatcher, $ctx, 'excerpt', [
                'temperature' => 0.3,
                'max_tokens'  => 150,
            ]);
        }

        // 5. Tags
        if (!empty($args['gen_tags'])) {
            $result['tags'] = self::generate_single($dispatcher, $ctx, 'tags', [
                'temperature' => 0.3,
                'max_tokens'  => 100,
            ]);
        }

        return $result;
    }

    /**
     * Default prompt templates for each metadata type.
     *
     * @param string $type
     * @param array  $ctx
     * @return string
     */
    private static function build_default_prompt(string $type, array $ctx): string
    {
        $title    = $ctx['title'] ?: $ctx['topic'];
        $topic    = $ctx['topic'];
        $keywords = $ctx['keywords'];
        $content  = $ctx['content'];

        switch ($type) {
            case 'title':
                return "为以下主题生成一个 SEO 友好的中文标题(8-15字),只返回标题文本,不要其他内容。\n\n主题:{$topic}\n关键词:{$keywords}";

            case 'meta':
                $excerpt = mb_substr(wp_strip_all_tags($content), 0, 500);
                return "为以下文章生成 150-160 字的 SEO meta description,包含主关键词,只返回描述文本。\n\n标题:{$title}\n关键词:{$keywords}\n\n正文摘要:{$excerpt}";

            case 'keyword':
                $body = mb_substr(wp_strip_all_tags($content), 0, 800);
                return "从以下文章中提取 1 个焦点关键词和 5 个长尾关键词,用逗号分隔,只返回关键词。\n\n标题:{$title}\n\n正文:{$body}";

            case 'excerpt':
                $body = mb_substr(wp_strip_all_tags($content), 0, 800);
                return "为以下文章生成 100 字以内的摘要,只返回摘要文本。\n\n标题:{$title}\n\n正文:{$body}";

            case 'tags':
                return "为以下文章生成 5-8 个标签,用逗号分隔,只返回标签。\n\n标题:{$title}\n关键词:{$keywords}";

            default:
                return '';
        }
    }

    /**
     * Resolve a custom prompt template (if configured) or fall back to default.
     *
     * @param array  $cfg
     * @param string $type
     * @param string $default
     * @return string
     */
    private static function resolve_prompt(array $cfg, string $type, string $default): string
    {
        $key = 'custom_' . $type . '_prompt';
        if (!empty($cfg[$key])) {
            return $cfg[$key];
        }
        return $default;
    }

    /**
     * Generate a single piece of SEO metadata via AI dispatch.
     *
     * @param AIDispatcher $dispatcher
     * @param array        $ctx       Generation context
     * @param string       $type      One of: title, meta, keyword, excerpt, tags
     * @param array        $opts      temperature, max_tokens, optional system prompt
     * @return string
     */
    private static function generate_single(
        AIDispatcher $dispatcher,
        array $ctx,
        string $type,
        array $opts
    ): string {
        try {
            $cfg     = $ctx['cfg'];
            $title   = $ctx['title'] ?: $ctx['topic'];
            $topic   = $ctx['topic'];
            $keywords = $ctx['keywords'];

            // Build prompt: custom template or default
            $default_prompt = self::build_default_prompt($type, $ctx);
            $prompt = self::resolve_prompt($cfg, $type, $default_prompt);

            // Apply placeholder substitution for custom prompts
            if (!empty($cfg['custom_' . $type . '_prompt'])) {
                $prompt = str_replace(
                    ['{title}', '{keywords}', '{topic}'],
                    [$title, $keywords, $topic],
                    $prompt
                );
            }

            $messages = [];
            if (!empty($opts['system'])) {
                $messages[] = ['role' => 'system', 'content' => $opts['system']];
            }
            $messages[] = ['role' => 'user', 'content' => $prompt];

            $r = $dispatcher->chat(
                $messages,
                [
                    'provider'  => $ctx['provider'],
                    'model'     => $ctx['model'],
                    'temperature' => $opts['temperature'] ?? 0.3,
                    'max_tokens'  => $opts['max_tokens'] ?? 200,
                    'module'    => 'content_writer',
                    'user_id'   => $ctx['user_id'],
                ],
                ['fallback_providers' => []]
            );
            return trim(wp_strip_all_tags($r['content']), "\"'“”‘’");
        } catch (\Throwable $e) {
            return ''; // 静默失败
        }
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
