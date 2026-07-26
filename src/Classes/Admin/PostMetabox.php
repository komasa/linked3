<?php

declare(strict_types=1);
/**
 * 文章编辑页 Metabox — v2.8.0 深度对标 aipower Post Enhancer
 *
 * 功能:
 *   - 文本操作 (7 个): 改写/扩写/纠正语法/总结/生成大纲/生成FAQ/简化语气
 *   - AI 生成标题/摘要/标签/Meta
 *   - AI 生成特色图片
 *   - SEO 评分
 *   - 翻译 (中→英 / 英→中)
 *
 * @package Linked3
 * @subpackage Classes\Admin
 */

namespace Linked3\Classes\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class PostMetabox
{
    static function register(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('wp_ajax_linked3_metabox_ai', [__CLASS__, 'ajax_ai']);
        add_action('wp_ajax_linked3_metabox_process_text', [__CLASS__, 'ajax_process_text']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    static function add_metabox(): void {
        $types = ['post', 'page', 'product'];
        foreach ($types as $type) {
            add_meta_box(
                'linked3_ai_metabox',
                'Linked3 AI 辅助',
                [__CLASS__, 'render_metabox'],
                $type,
                'side',
                'high'
            );
        }
    }

    public static function render_metabox($post) : void {
        wp_nonce_field('linked3_metabox', 'linked3_metabox_nonce');
        $score = get_post_meta($post->ID, '_linked3_seo_score', true);
        ?>
        <div id="linked3-metabox">
            <?php self::render_text_ops(); ?>
            <?php self::render_article_ai_ops($score); ?>
            <div id="linked3-mb-result" style="margin-top:10px;font-size:12px;max-height:300px;overflow-y:auto;"></div>
        </div>
        <?php
        // v29.1.0 Step 4: Inline JS extracted to admin/js/linked3-metabox.js
    }

    /**
     * 渲染文本操作按钮组 (7+4 个操作).
     */
    private static function render_text_ops(): void
    {
        ?>
        <div style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;">
            <p style="font-weight:600;margin:0 0 6px;"><?php echo esc_html__('📝 文本操作', 'linked3'); ?></p>
            <p style="margin:0 0 4px;">
                <button type="button" class="button button-small linked3-mb-text" data-action="rewrite"><?php echo esc_html__('改写', 'linked3'); ?></button>
                <button type="button" class="button button-small linked3-mb-text" data-action="expand"><?php echo esc_html__('扩写', 'linked3'); ?></button>
                <button type="button" class="button button-small linked3-mb-text" data-action="fix_grammar"><?php echo esc_html__('纠错', 'linked3'); ?></button>
                <button type="button" class="button button-small linked3-mb-text" data-action="summarize"><?php echo esc_html__('总结', 'linked3'); ?></button>
            </p>
            <p style="margin:0 0 4px;">
                <button type="button" class="button button-small linked3-mb-text" data-action="outline"><?php echo esc_html__('大纲', 'linked3'); ?></button>
                <button type="button" class="button button-small linked3-mb-text" data-action="faqs">FAQ</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="simplify"><?php echo esc_html__('简化', 'linked3'); ?></button>
                <button type="button" class="button button-small linked3-mb-text" data-action="translate_en"><?php echo esc_html__('中→英', 'linked3'); ?></button>
            </p>
            <p style="margin:0 0 4px;">
                <button type="button" class="button button-small linked3-mb-text" data-action="translate_zh"><?php echo esc_html__('英→中', 'linked3'); ?></button>
                <button type="button" class="button button-small linked3-mb-text" data-action="make_longer"><?php echo esc_html__('加长', 'linked3'); ?></button>
                <button type="button" class="button button-small linked3-mb-text" data-action="make_shorter"><?php echo esc_html__('缩短', 'linked3'); ?></button>
            </p>
        </div>
        <?php
    }

    /**
     * 渲染文章级 AI 操作 + SEO 评分.
     */
    private static function render_article_ai_ops($score): void
    {
        ?>
        <div style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;">
            <p style="font-weight:600;margin:0 0 6px;"><?php echo esc_html__('🎯 文章 AI', 'linked3'); ?></p>
            <p>
                <button type="button" class="button linked3-mb-btn" data-action="title"><?php echo esc_html__('生成标题', 'linked3'); ?></button>
                <button type="button" class="button linked3-mb-btn" data-action="excerpt"><?php echo esc_html__('生成摘要', 'linked3'); ?></button>
            </p>
            <p>
                <button type="button" class="button linked3-mb-btn" data-action="tags"><?php echo esc_html__('生成标签', 'linked3'); ?></button>
                <button type="button" class="button linked3-mb-btn" data-action="meta"><?php echo esc_html__('生成 SEO Meta', 'linked3'); ?></button>
            </p>
            <p>
                <button type="button" class="button button-primary linked3-mb-btn" data-action="image"><?php echo esc_html__('生成特色图片', 'linked3'); ?></button>
            </p>
        </div>

        <?php if ($score) : ?>
        <p><strong>SEO 评分:</strong> <span id="linked3-seo-score"><?php echo esc_html($score); ?>/100</span></p>
        <?php endif;
    }

    /**
     * v29.1.0 Step 4: Enqueue metabox JS on post-edit screens.
     * Inline JS extracted to admin/js/linked3-metabox.js
     */
    static function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_enqueue_script(
            'linked3-metabox',
            LINKED3_URL . 'admin/js/linked3-metabox.js',
            [],
            LINKED3_VERSION,
            true
        );
        wp_localize_script('linked3-metabox', 'linked3_metabox', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id'  => (int) ($_GET['post'] ?? 0),
        ]);
    }

    static function save_metabox($post_id, $post): void {
        if (!isset($_POST['linked3_metabox_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linked3_metabox_nonce'])), 'linked3_metabox')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    }

    /**
     * AJAX: 文章级 AI 操作 (标题/摘要/标签/Meta/图片)
     */
    static function ajax_ai(): void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_metabox')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        $sub = sanitize_text_field($_POST['sub_action'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_strip_all_tags(wp_unslash($_POST['content'] ?? ''));
        $post_id = (int) ($_POST['post_id'] ?? 0);

        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            // v2.8.0: 用用户配置的默认 Provider,而非硬编码 openai
            $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $saved_models[$provider] ?? 'gpt-4o-mini';
            $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            $api_key = $keys[$provider] ?? '';

            switch ($sub) {
                case 'title':
                    $prompt = __('为以下文章内容生成 5 个吸引人的标题(每行一个,不要编号):\n\n', 'linked3') . mb_substr($content, 0, 2000);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.7, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    $titles = array_filter(array_map('trim', explode("\n", $r['content'])));
                    wp_send_json_success(['title' => $titles[0] ?? '', 'message' => __('已生成标题(可选其他): ', 'linked3') . implode(' / ', array_slice($titles, 1, 3))]);
                    break;

                case 'excerpt':
                    $prompt = __('为以下文章生成一段 100-150 字的摘要:\n\n标题: {$title}\n\n', 'linked3') . mb_substr($content, 0, 2000);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    wp_send_json_success(['excerpt' => trim($r['content'])]);
                    break;

                case 'tags':
                    $prompt = __('为以下文章生成 5-8 个标签(逗号分隔,不要编号):\n\n标题: {$title}\n\n', 'linked3') . mb_substr($content, 0, 1500);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    wp_send_json_success(['tags' => trim($r['content'])]);
                    break;

                case 'meta':
                    $prompt = __('为以下文章生成 SEO meta description (150 字以内):\n\n标题: {$title}\n\n', 'linked3') . mb_substr($content, 0, 1500);
                    $r = $dispatcher->chat(
                        [['role' => 'user', 'content' => $prompt]],
                        ['provider' => $provider, 'model' => $model, 'temperature' => 0.3, 'module' => 'metabox'],
                        ['fallback_providers' => ['deepseek', 'zhipu']]
                    );
                    $meta = trim($r['content']);
                    if ($post_id) {
                        update_post_meta($post_id, '_linked3_meta_description', $meta);
                        update_post_meta($post_id, '_aioseo_description', $meta);
                        update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta);
                    }
                    wp_send_json_success(['message' => __('SEO Meta 已保存: ', 'linked3') . $meta]);
                    break;

                case 'image':
                    if (empty($keys['openai'])) {
                        wp_send_json_error(['message' => __('需要 OpenAI API Key 生成图片 (在 API 设置里配置)', 'linked3')]);
                    }
                    $prompt = __('为文章《{$title}》生成一张配图,风格: 现代简约,横版', 'linked3');
                    $url = 'https://api.openai.com/v1/images/generations';
                    $resp = \Linked3\Includes\Http\SafeRemote::post($url, [
                        'timeout' => 60,
                        'headers' => ['Authorization' => 'Bearer ' . $keys['openai'], 'Content-Type' => 'application/json'],
                        'body' => wp_json_encode(['model' => 'dall-e-3', 'prompt' => $prompt, 'n' => 1, 'size' => '1792x1024']),
                        'allowed_hosts' => ['api.openai.com'],
                    ]);
                    if (is_wp_error($resp)) {
                        wp_send_json_error(['message' => $resp->get_error_message()]);
                    }
                    $body = json_decode(wp_remote_retrieve_body($resp), true);
                    $image_url = $body['data'][0]['url'] ?? '';
                    if (!$image_url) {
                        wp_send_json_error(['message' => __('图片生成失败', 'linked3')]);
                    }
                    if (!function_exists('media_handle_sideload')) {
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                    }
                    $tmp = download_url($image_url);
                    if (is_wp_error($tmp)) wp_send_json_error(['message' => __('下载失败', 'linked3')]);
                    $file = ['name' => 'linked3-' . time() . '.png', 'tmp_name' => $tmp];
                    $attach_id = media_handle_sideload($file, $post_id);
                    if (is_wp_error($attach_id)) wp_send_json_error(['message' => __('媒体库插入失败', 'linked3')]);
                    set_post_thumbnail($post_id, $attach_id);
                    wp_send_json_success(['image_url' => wp_get_attachment_url($attach_id), 'message' => __('特色图片已设置', 'linked3')]);
                    break;
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: 文本操作 (aipower Post Enhancer 对标 v2.8.0)
     *
     * 11 个操作: rewrite/expand/fix_grammar/summarize/outline/faqs/simplify/
     *            translate_en/translate_zh/make_longer/make_shorter
     */
    static function ajax_process_text(): void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_metabox')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }
        $action_key = sanitize_key($_POST['process_action'] ?? '');
        $text = wp_strip_all_tags(wp_unslash($_POST['text'] ?? ''));
        if (empty($text) || mb_strlen($text) < 5) {
            wp_send_json_error(['message' => __('文本太短', 'linked3')]);
        }

        // 11 个操作的 prompt 模板 (aipower 风格)
        $prompts = [
            'rewrite'       => __('改写以下文本以提升清晰度和吸引力,保持原意不变。只返回改写后的文本,不要解释:\n\n"%s"', 'linked3'),
            'expand'         => __('扩写以下内容,补充更多细节和例子。只返回扩写后的文本,不要解释:\n\n"%s"', 'linked3'),
            'fix_grammar'   => __('纠正以下文本中的拼写和语法错误。只返回纠正后的文本,不要解释:\n\n"%s"', 'linked3'),
            'summarize'     => __('用 3-5 句简洁的话总结以下文本,保留关键事实和语气。只返回总结,不要解释:\n\n"%s"', 'linked3'),
            'outline'       => __('为以下文本生成清晰的大纲,使用 Markdown 格式 (## H2, ### H3),必要时加项目符号。只返回大纲:\n\n"%s"', 'linked3'),
            'faqs'          => __('基于以下文本生成 5-7 个相关的 FAQ 问题和简短答案,用 Markdown Q/A 格式。只返回 FAQ:\n\n"%s"', 'linked3'),
            'simplify'      => __('用友好、简单的语气 (7-8 年级可读性) 重写以下文本,保持原意和结构。只返回重写后的文本:\n\n"%s"', 'linked3'),
            'translate_en'  => __('将以下中文文本翻译成地道、流畅的英文。只返回译文,不要解释:\n\n"%s"', 'linked3'),
            'translate_zh'  => __('将以下英文文本翻译成地道、流畅的中文。只返回译文,不要解释:\n\n"%s"', 'linked3'),
            'make_longer'   => __('在不改变原意的前提下,扩展以下文本使其更长更详细。只返回扩展后的文本:\n\n"%s"', 'linked3'),
            'make_shorter'  => __('在不改变原意的前提下,精简以下文本使其更短。只返回精简后的文本:\n\n"%s"', 'linked3'),
        ];
        if (!isset($prompts[$action_key])) {
            wp_send_json_error(['message' => __('未知操作: ', 'linked3') . $action_key]);
        }

        // 截断文本避免超长
        $truncated = mb_substr($text, 0, 3000);
        $prompt = sprintf($prompts[$action_key], $truncated);

        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $saved_models[$provider] ?? 'gpt-4o-mini';

            $r = $dispatcher->chat(
                [['role' => 'user', 'content' => $prompt]],
                [
                    'provider' => $provider, 'model' => $model,
                    'temperature' => 0.6, 'max_tokens' => 2000, 'module' => 'metabox',
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );
            wp_send_json_success(['result' => trim($r['content']), 'usage' => $r['usage']]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
