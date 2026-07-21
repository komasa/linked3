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
    public static function register()
    : void {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('wp_ajax_linked3_metabox_ai', [__CLASS__, 'ajax_ai']);
        add_action('wp_ajax_linked3_metabox_process_text', [__CLASS__, 'ajax_process_text']);
    }

    public static function add_metabox()
    : void {
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

    public static function render_metabox($post) : mixed {
        wp_nonce_field('linked3_metabox', 'linked3_metabox_nonce');
        $score = get_post_meta($post->ID, '_linked3_seo_score', true);
        ?>
        <div id="linked3-metabox">
            <?php self::render_text_ops(); ?>
            <?php self::render_article_ai_ops($score); ?>
            <div id="linked3-mb-result" style="margin-top:10px;font-size:12px;max-height:300px;overflow-y:auto;"></div>
        </div>
        <?php self::render_metabox_script($post); ?>
        <?php
    }

    /**
     * 渲染文本操作按钮组 (7+4 个操作).
     */
    private static function render_text_ops(): void
    {
        ?>
        <div style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px;">
            <p style="font-weight:600;margin:0 0 6px;">📝 文本操作</p>
            <p style="margin:0 0 4px;">
                <button type="button" class="button button-small linked3-mb-text" data-action="rewrite">改写</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="expand">扩写</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="fix_grammar">纠错</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="summarize">总结</button>
            </p>
            <p style="margin:0 0 4px;">
                <button type="button" class="button button-small linked3-mb-text" data-action="outline">大纲</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="faqs">FAQ</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="simplify">简化</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="translate_en">中→英</button>
            </p>
            <p style="margin:0 0 4px;">
                <button type="button" class="button button-small linked3-mb-text" data-action="translate_zh">英→中</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="make_longer">加长</button>
                <button type="button" class="button button-small linked3-mb-text" data-action="make_shorter">缩短</button>
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
            <p style="font-weight:600;margin:0 0 6px;">🎯 文章 AI</p>
            <p>
                <button type="button" class="button linked3-mb-btn" data-action="title">生成标题</button>
                <button type="button" class="button linked3-mb-btn" data-action="excerpt">生成摘要</button>
            </p>
            <p>
                <button type="button" class="button linked3-mb-btn" data-action="tags">生成标签</button>
                <button type="button" class="button linked3-mb-btn" data-action="meta">生成 SEO Meta</button>
            </p>
            <p>
                <button type="button" class="button button-primary linked3-mb-btn" data-action="image">生成特色图片</button>
            </p>
        </div>

        <?php if ($score) : ?>
        <p><strong>SEO 评分:</strong> <span id="linked3-seo-score"><?php echo esc_html($score); ?>/100</span></p>
        <?php endif;
    }

    /**
     * 渲染 metabox 内联 JavaScript (事件绑定 + AJAX).
     */
    public function render_metabox_script($post)
    {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('linked3_metabox');
        $settings = $this->get_metabox_settings($post);
        $providers = $this->get_provider_options();
        $templates = $this->get_template_options();
        ?>
        <script>
        window.linked3Metabox = {
            ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
            nonce: '<?php echo esc_js($nonce); ?>',
            postId: <?php echo (int)$post->ID; ?>,
            settings: <?php echo wp_json_encode($settings); ?>,
            providers: <?php echo wp_json_encode($providers); ?>,
            templates: <?php echo wp_json_encode($templates); ?>,
        };
        </script>
        <?php
    }

    private function get_metabox_settings($post): array {
        $settings = get_post_meta($post->ID, '_linked3_metabox_settings', true);
        return is_array($settings) ? $settings : [
            'provider' => get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow'),
            'model' => '',
            'template' => 'blog_post',
            'word_count' => 1200,
            'tone' => 'professional',
        ];
    }

    private function get_provider_options(): array {
        $providers = [];
        $saved = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        foreach ($saved as $slug => $keys) {
            if (empty($keys)) continue;
            $providers[] = [
                'slug' => $slug,
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'model' => $models[$slug] ?? '',
            ];
        }
        return $providers ?: [['slug' => 'siliconflow', 'name' => 'SiliconFlow', 'model' => 'Qwen/Qwen2.5-7B-Instruct']];
    }

    private function get_template_options(): array {
        if (class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            $tplMgr = new \Linked3\Classes\Templates\TemplateManager();
            $all = $tplMgr->get_all();
            return array_map(fn($t) => ['id' => $t['id'] ?? 0, 'name' => $t['name'] ?? ''], $all);
        }
        return [['id' => 1, 'name' => 'Blog Post']];
    }

    public static function save_metabox($post_id, $post)
    : void {
        if (!isset($_POST['linked3_metabox_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linked3_metabox_nonce'])), 'linked3_metabox')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    }

    /**
     * AJAX: 文章级 AI 操作 (标题/摘要/标签/Meta/图片)
     */
    public function ajax_ai()
    {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_metabox')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $input = $this->parse_ai_input();
        $content = $this->generate_ai_content($input);
        if (is_wp_error($content)) {
            wp_send_json_error(['message' => $content->get_error_message()]);
        }
        $content = $this->post_process_content($content, $input);
        $this->save_to_post($input['post_id'], $content, $input);
        wp_send_json_success(['content' => $content, 'message' => __('生成成功', 'linked3-ai')]);
    }

    private function parse_ai_input(): array {
        return [
            'post_id'   => (int) ($_POST['post_id'] ?? 0),
            'prompt'    => sanitize_textarea_field($_POST['prompt'] ?? ''),
            'provider'  => sanitize_text_field($_POST['provider'] ?? 'siliconflow'),
            'model'     => sanitize_text_field($_POST['model'] ?? ''),
            'template'  => sanitize_text_field($_POST['template'] ?? 'blog_post'),
            'word_count'=> (int) ($_POST['word_count'] ?? 1200),
            'tone'      => sanitize_text_field($_POST['tone'] ?? 'professional'),
        ];
    }

    private function generate_ai_content(array $input): string|\WP_Error {
        if (empty($input['prompt'])) {
            return new \WP_Error('empty_prompt', __('请输入提示词', 'linked3-ai'));
        }
        try {
            $result = AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $input['prompt']]],
                [
                    'provider' => $input['provider'],
                    'model' => $input['model'] ?: 'Qwen/Qwen2.5-7B-Instruct',
                    'temperature' => 0.7,
                    'max_tokens' => $input['word_count'] * 2,
                    'module' => 'metabox',
                ],
                ['fallback_providers' => ['deepseek', 'zhipu']]
            );
            return $result['content'] ?? '';
        } catch (\Throwable $e) {
            return new \WP_Error('ai_failed', 'AI 调用失败: ' . $e->getMessage());
        }
    }

    private function post_process_content(string $content, array $input): string {
        if (class_exists('\\Linked3\\Classes\\Core\\AIEnhancer')) {
            $enhancer = new \Linked3\Classes\Core\AIEnhancer();
            $content = $enhancer->append_identifier_suffix($content);
        }
        return $content;
    }

    private function save_to_post(int $postId, string $content, array $input): void {
        if ($postId <= 0) return;
        $post = get_post($postId);
        if (!$post) return;
        $existing = $post->post_content;
        $newContent = $existing ? $existing . "\n\n" . $content : $content;
        wp_update_post(['ID' => $postId, 'post_content' => wp_slash($newContent)]);
    }

    /**
     * AJAX: 文本操作 (aipower Post Enhancer 对标 v2.8.0)
     *
     * 11 个操作: rewrite/expand/fix_grammar/summarize/outline/faqs/simplify/
     *            translate_en/translate_zh/make_longer/make_shorter
     */
    public static function ajax_process_text()
    : void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_metabox')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }
        $action_key = sanitize_key($_POST['process_action'] ?? '');
        $text = wp_strip_all_tags(wp_unslash($_POST['text'] ?? ''));
        if (empty($text) || mb_strlen($text) < 5) {
            wp_send_json_error(['message' => __('文本太短', 'linked3-ai')]);
        }

        // 11 个操作的 prompt 模板 (aipower 风格)
        $prompts = [
            'rewrite'       => '改写以下文本以提升清晰度和吸引力,保持原意不变。只返回改写后的文本,不要解释:\n\n"%s"',
            'expand'         => '扩写以下内容,补充更多细节和例子。只返回扩写后的文本,不要解释:\n\n"%s"',
            'fix_grammar'   => '纠正以下文本中的拼写和语法错误。只返回纠正后的文本,不要解释:\n\n"%s"',
            'summarize'     => '用 3-5 句简洁的话总结以下文本,保留关键事实和语气。只返回总结,不要解释:\n\n"%s"',
            'outline'       => '为以下文本生成清晰的大纲,使用 Markdown 格式 (## H2, ### H3),必要时加项目符号。只返回大纲:\n\n"%s"',
            'faqs'          => '基于以下文本生成 5-7 个相关的 FAQ 问题和简短答案,用 Markdown Q/A 格式。只返回 FAQ:\n\n"%s"',
            'simplify'      => '用友好、简单的语气 (7-8 年级可读性) 重写以下文本,保持原意和结构。只返回重写后的文本:\n\n"%s"',
            'translate_en'  => '将以下中文文本翻译成地道、流畅的英文。只返回译文,不要解释:\n\n"%s"',
            'translate_zh'  => '将以下英文文本翻译成地道、流畅的中文。只返回译文,不要解释:\n\n"%s"',
            'make_longer'   => '在不改变原意的前提下,扩展以下文本使其更长更详细。只返回扩展后的文本:\n\n"%s"',
            'make_shorter'  => '在不改变原意的前提下,精简以下文本使其更短。只返回精简后的文本:\n\n"%s"',
        ];
        if (!isset($prompts[$action_key])) {
            wp_send_json_error(['message' => __('未知操作: ', 'linked3-ai') . $action_key]);
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
