<?php

declare(strict_types=1);
/**
 * EcosystemSynergyTrait — Synergy flow + content/template/image/draft handlers.
 *
 * @package Linked3\Content
 */

namespace Linked3\Classes\Content;

use Linked3\Includes\Log\Logger;

if (!defined('ABSPATH')) exit;

trait EcosystemSynergyTrait
{
    /**
     * 生态协同生产 — 一键全流程
     */
    public static function ajax_synergy() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'content');
        $platform = sanitize_text_field($_POST['platform'] ?? 'generic');

        if (empty($topic)) wp_send_json_error(['message' => __('请输入主题', 'linked3')]);

        @set_time_limit(180);

        try {
            // 1. 关键词生成
            $keywords = EcosystemKeywordService::generate_keywords($topic);

            // 2. 模版加载
            $template = EcosystemContentService::load_template($category);

            // 3. 内容写作
            $content = EcosystemContentService::generate_content($topic, $keywords, $template);

            // v11.0.9 #4: AI生成失败时明确报错, 不返回假大空内容
            if (empty($content)) {
                wp_send_json_error(['message' => __('文章生成失败 — 请确认已配置AI API Key (设置→API设置), 且API可用。拒绝返回假大空模板内容。', 'linked3')]);
            }

            // 4. 图片配置
            $images = EcosystemImageService::generate_images($content, $keywords);

            // 5. 质检
            $quality = EcosystemImageService::quality_check($keywords, $template, $content, $images);

            wp_send_json_success([
                'ir' => [
                    'keywords' => $keywords,
                    'template' => $template,
                    'content' => $content,
                    'images' => $images,
                ],
                'quality' => $quality,
                'meta' => [
                    'topic' => $topic,
                    'category' => $category,
                    'platform' => $platform,
                    'version' => '10.7.0',
                ],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('生态生产失败: ', 'linked3') . $e->getMessage()]);
        }
    }

    /**
     * 内容写作
     */
    public static function ajax_content() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $keywords_str = sanitize_text_field($_POST['keywords'] ?? '');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $style_dna = sanitize_text_field($_POST['style_dna'] ?? '');
        $humanize_modules = json_decode($_POST['humanize_modules'] ?? '[]', true) ?: [];

        // v17.2: 注入风格DNA到系统指令
        if ($style_dna && class_exists('\Linked3\Classes\Content\SystemInstructionBuilder')) {
            $builder = new \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder();
            $system_instruction = $builder->build([
                'role' => __('专业内容写作', 'linked3'),
                'tone' => $tone,
                'style_dna' => $style_dna,
                'anti_ai' => !empty($humanize_modules),
            ]);
        }
        $word_count = intval($_POST['word_count'] ?? 800);
        // v11.5.0: 接收行业变体 (P2) — 消费G3的50场景母版
        $industry = sanitize_key($_POST['industry'] ?? 'general');

        if (empty($topic)) wp_send_json_error(['message' => __('请输入主题', 'linked3')]);

        try {
            $keywords = array_filter(array_map('trim', explode(',', $keywords_str)));
            // v11.5.0: 若指定行业变体, 用行业母版增强prompt
            if ($industry !== 'general' && class_exists('\Linked3\Classes\Content\CloudTemplateFactory')) {
                try {
                    $factory = new \CloudTemplateFactory();
                    $tpl = $factory->load_template_by_category_and_industry('content', $industry);
                    if (!empty($tpl['config']['role'])) {
                        $topic = $topic . "\n[行业调性: " . $tpl['config']['role'] . " | 风格: " . ($tpl['config']['style'] ?? '') . "]";
                    }
                } catch (\Throwable $e) { Logger::instance()->warning('ai', $e->getMessage()); }
            }
            $content = EcosystemContentService::generate_content($topic, $keywords, [], $tone, $word_count);
            $checked = EcosystemContentService::self_check_content($content);

            wp_send_json_success([
                'content' => $checked,
                'word_count' => mb_strlen($checked),
                'checked' => true,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('内容生成失败: ', 'linked3') . $e->getMessage()]);
        }
    }

    /**
     * v10.7.0 云模版保存 — 保存到wp_options, 跨生态共享
     */
    public static function ajax_template_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $template_json = wp_unslash($_POST['template'] ?? '');
        $template = json_decode($template_json, true);
        if (!is_array($template)) wp_send_json_error(['message' => __('模版数据格式错误', 'linked3')]);

        $name = sanitize_text_field($template['name'] ?? '');
        $type = sanitize_key($template['type'] ?? 'content');
        if (empty($name)) wp_send_json_error(['message' => __('模版名称不能为空', 'linked3')]);

        // v11.3.3: 接收 fork_id (派生模版的源模版ID, 可选)
        $fork_id = sanitize_text_field($_POST['fork_id'] ?? ($template['fork_id'] ?? ''));

        $config = self::sanitize_template_config($template['config'] ?? []);

        // 保存到 wp_options (跨生态共享池)
        $option_key = LINKED3_OPTION_PREFIX . 'cloud_templates';
        $all_templates = (array) get_option($option_key, []);
        $template_id = sanitize_title($name) . '_' . wp_rand(1000, 9999);
        $all_templates[$template_id] = [
            'id'        => $template_id,
            'name'      => $name,
            'type'      => $type,
            'config'    => $config,
            'shared'    => true, // 跨生态共享标记
            'fork_id'   => $fork_id, // v11.3.3: 派生源模版ID (空=原创)
            'updated_at'=> current_time('mysql'),
        ];
        update_option($option_key, $all_templates, false);

        wp_send_json_success([
            'template_id' => $template_id,
            'name'        => $name,
            'shared'      => true,
            'message'     => __('模版已保存并加入跨生态共享池', 'linked3'),
        ]);
    }

    /**
     * Sanitize the 10-field template config from user input.
     */
    private static function sanitize_template_config(array $raw): array
    {
        $csv_fields = ['goals', 'skills', 'limit', 'step'];

        $config = [];
        foreach ($csv_fields as $field) {
            $config[$field] = array_filter(array_map('sanitize_text_field', explode(',', $raw[$field] ?? '')));
        }

        $config['profile']    = sanitize_textarea_field($raw['profile'] ?? '');
        $config['role']       = sanitize_textarea_field($raw['role'] ?? '');
        $config['scene']      = sanitize_textarea_field($raw['scene'] ?? '');
        $config['background'] = sanitize_textarea_field($raw['background'] ?? '');
        $config['style']      = sanitize_text_field($raw['style'] ?? '');
        $config['output']     = sanitize_textarea_field($raw['output'] ?? '');

        return $config;
    }

    /**
     * v10.7.0 图片设置保存 — 保存到wp_options
     */
    public static function ajax_image_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $settings_json = wp_unslash($_POST['settings'] ?? '');
        $settings = json_decode($settings_json, true);
        if (!is_array($settings)) wp_send_json_error(['message' => __('设置数据格式错误', 'linked3')]);

        // 安全清洗
        $clean = [
            'provider'        => sanitize_key($settings['provider'] ?? 'openai'),
            'resolution'      => sanitize_text_field($settings['resolution'] ?? '1280*1280'),
            'insert_position' => sanitize_key($settings['insert_position'] ?? 'after_first_h2'),
            'layouts'         => array_map('sanitize_key', (array) ($settings['layouts'] ?? [])),
            'updated_at'      => current_time('mysql'),
        ];

        // 解析分辨率到宽高
        $parts = explode('*', $clean['resolution']);
        if (count($parts) === 2) {
            $clean['img_width'] = intval($parts[0]);
            $clean['img_height'] = intval($parts[1]);
        }

        update_option(LINKED3_OPTION_PREFIX . 'image_settings', $clean, false);

        wp_send_json_success([
            'settings' => $clean,
            'message'  => __('图片设置已保存', 'linked3'),
        ]);
    }

    // ================================================================
    // v10.7.3 SOP闭环 — 保存草稿
    // ================================================================

    /**
     * v10.7.3 SOP闭环 — 将生态生产结果保存为WordPress草稿
     */
    public static function ajax_save_draft() : void {
        if (!current_user_can('edit_posts')) {
            Logger::instance()->error('ai', '[linked3 eco_save_draft] 权限不足, user_id=' . get_current_user_id());
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) {
            Logger::instance()->error('ai', '[linked3 eco_save_draft] nonce验证失败');
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_unslash($_POST['content'] ?? '');

        // R5修复: 空内容兜底 — 返回明确错误而非让wp_insert_post静默失败
        if (empty($title)) {
            Logger::instance()->error('ai', '[linked3 eco_save_draft] 标题为空');
            wp_send_json_error(['message' => __('标题不能为空', 'linked3')]);
        }
        if (empty($content)) {
            Logger::instance()->error('ai', '[linked3 eco_save_draft] 内容为空, title=' . $title);
            wp_send_json_error(['message' => __('内容不能为空, 请先完成内容生成步骤', 'linked3')]);
        }

        // R5修复: 内容长度检查 (WordPress wp_insert_post 对极短内容可能失败)
        $content_plain = wp_strip_all_tags($content);
        $content_plain = trim(preg_replace('/\s+/', ' ', $content_plain));
        if (strlen($content_plain) < 10) {
            Logger::instance()->error('ai', '[linked3 eco_save_draft] 内容过短 (' . strlen($content_plain) . ' chars), title=' . $title);
            wp_send_json_error(['message' => __('内容过短, 请检查内容生成是否完整', 'linked3')]);
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'post',
        ], true);

        if (is_wp_error($post_id)) {
            Logger::instance()->error('ai', '[linked3 eco_save_draft] wp_insert_post失败: ' . $post_id->get_error_message() . ', title=' . $title);
            wp_send_json_error(['message' => __('保存失败: ', 'linked3') . $post_id->get_error_message()]);
        }

        Logger::instance()->info('ai', '[linked3 eco_save_draft] 成功, post_id=' . $post_id . ', title=' . $title . ', content_len=' . strlen($content));

        wp_send_json_success([
            'post_id'  => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'message'  => __('已保存为草稿, ID: ', 'linked3') . $post_id,
        ]);
    }
}
