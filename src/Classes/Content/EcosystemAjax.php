<?php

declare(strict_types=1);
/**
 * Linked3 Ecosystem AJAX Handler v10.7.0 — 写作生态统一API
 *
 * 注册AJAX endpoint:
 *   - linked3_eco_synergy: 生态协同生产 (一键全流程)
 *   - linked3_eco_keywords: 关键词生成
 *   - linked3_eco_content: 内容写作
 *   - linked3_eco_template_save: 云模版保存 (v10.7.0新增)
 *   - linked3_eco_image_save: 图片设置保存 (v10.7.0新增)
 *
 * @package Linked3\Content
 * @version 10.7.1
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

class EcosystemAjax {

    public static function register() : void {
        add_action('wp_ajax_linked3_eco_synergy', [__CLASS__, 'ajax_synergy']);
        add_action('wp_ajax_linked3_eco_keywords', [__CLASS__, 'ajax_keywords']);
        add_action('wp_ajax_linked3_eco_content', [__CLASS__, 'ajax_content']);
        add_action('wp_ajax_linked3_eco_template_save', [__CLASS__, 'ajax_template_save']);
        add_action('wp_ajax_linked3_eco_image_save', [__CLASS__, 'ajax_image_save']);
        // v10.7.1: 全功能链新增端点
        add_action('wp_ajax_linked3_eco_hot_collect', [__CLASS__, 'ajax_hot_collect']);
        add_action('wp_ajax_linked3_eco_keywords_save', [__CLASS__, 'ajax_keywords_save']);
        // v16.0.14 [公理α/β]: 长尾词使用状态持久化 AJAX
        add_action('wp_ajax_linked3_eco_tail_used_save', [__CLASS__, 'ajax_tail_used_save']);
        add_action('wp_ajax_linked3_eco_longform_outline', [__CLASS__, 'ajax_longform_outline']);
        add_action('wp_ajax_linked3_eco_longform_section', [__CLASS__, 'ajax_longform_section']);
        add_action('wp_ajax_linked3_eco_csv_batch', [__CLASS__, 'ajax_csv_batch']);
        add_action('wp_ajax_linked3_eco_cron_enable', [__CLASS__, 'ajax_cron_enable']);
        add_action('wp_ajax_linked3_eco_cron_disable', [__CLASS__, 'ajax_cron_disable']);
        // v10.7.3: SOP闭环 — 保存草稿
        add_action('wp_ajax_linked3_eco_save_draft', [__CLASS__, 'ajax_save_draft']);
        // v10.7.4: 图片API保存 + 图片生成
        add_action('wp_ajax_linked3_save_image_api', [__CLASS__, 'ajax_save_image_api']);
        add_action('wp_ajax_linked3_eco_generate_images', [__CLASS__, 'ajax_generate_images']);

        if (function_exists('error_log')) {
            error_log('[linked3 v10.7.1] Ecosystem AJAX registered (12 endpoints)');
        }
    }

    /**
     * 生态协同生产 — 一键全流程
     */
    public static function ajax_synergy() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'content');
        $platform = sanitize_text_field($_POST['platform'] ?? 'generic');

        if (empty($topic)) wp_send_json_error(['message' => __('请输入主题', 'linked3-ai')]);

        @set_time_limit(180);

        try {
            // 1. 关键词生成
            $keywords = self::generate_keywords($topic);

            // 2. 模版加载
            $template = self::load_template($category);

            // 3. 内容写作
            $content = self::generate_content($topic, $keywords, $template);

            // v11.0.9 #4: AI生成失败时明确报错, 不返回假大空内容
            if (empty($content)) {
                wp_send_json_error(['message' => __('文章生成失败 — 请确认已配置AI API Key (设置→API设置), 且API可用。拒绝返回假大空模板内容。', 'linked3-ai')]);
            }

            // 4. 图片配置
            $images = self::generate_images($content, $keywords);

            // 5. 质检
            $quality = self::quality_check($keywords, $template, $content, $images);

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
            wp_send_json_error(['message' => __('生态生产失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }

    /**
     * 关键词生成
     */
    public static function ajax_keywords() : mixed {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $seed = sanitize_text_field($_POST['seed'] ?? '');
        $count = intval($_POST['count'] ?? 20);
        // v11.9.0 R1: 支持多热词批量生成长尾词 (不再只基于第一个热词)
        $multi_seeds_raw = isset($_POST['seeds']) ? wp_unslash($_POST['seeds']) : '';
        $mode = sanitize_key($_POST['mode'] ?? 'single');

        if (empty($seed) && empty($multi_seeds_raw)) {
            wp_send_json_error(['message' => __('请输入种子词或选择热词库', 'linked3-ai')]);
        }

        try {
            $all_keywords = [];
            $all_long_tail = [];

            if ($mode === 'multi' && !empty($multi_seeds_raw)) {
                // v11.9.0 R1: 多热词模式 — 每个热词各生成长尾词
                $seeds = array_filter(array_map('trim', explode("\n", $multi_seeds_raw)));
                $per_seed_count = max(3, intval($count / max(1, count($seeds))));
                foreach ($seeds as $s) {
                    $s = sanitize_text_field($s);
                    if (empty($s)) continue;
                    $kw = self::generate_keywords($s, $per_seed_count);
                    foreach ($kw as $k) {
                        if (!in_array($k, $all_keywords)) {
                            $all_keywords[] = $k;
                            if (mb_strlen($k) > 8) $all_long_tail[] = $k;
                        }
                    }
                    if (count($all_keywords) >= $count) break;
                }
                $all_keywords = array_slice($all_keywords, 0, $count);
            } else {
                // 单种子词模式 (兼容原逻辑)
                $all_keywords = self::generate_keywords($seed, $count);
                $all_long_tail = array_filter($all_keywords, function($k) { return mb_strlen($k) > 8; });
            }

            $classified = self::classify_keywords($all_keywords);

            wp_send_json_success([
                'keywords' => $all_keywords,
                'classified' => $classified,
                'long_tail' => $all_long_tail,
                'mode' => $mode,
                'seed_count' => $mode === 'multi' ? count($seeds ?? []) : 1,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('关键词生成失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }

    /**
     * 内容写作
     */
    public static function ajax_content() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $keywords_str = sanitize_text_field($_POST['keywords'] ?? '');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $style_dna = sanitize_text_field($_POST['style_dna'] ?? '');
        $humanize_modules = json_decode($_POST['humanize_modules'] ?? '[]', true) ?: [];
        
        // v17.2: 注入风格DNA到系统指令
        if ($style_dna && class_exists('\Linked3\Classes\Content\SystemInstructionBuilder')) {
            $builder = new \Linked3\Classes\ContentWriter\Prompt\SystemInstructionBuilder();
            $system_instruction = $builder->build([
                'role' => '专业内容写作',
                'tone' => $tone,
                'style_dna' => $style_dna,
                'anti_ai' => !empty($humanize_modules),
            ]);
        }
        $word_count = intval($_POST['word_count'] ?? 800);
        // v11.5.0: 接收行业变体 (P2) — 消费G3的50场景母版
        $industry = sanitize_key($_POST['industry'] ?? 'general');

        if (empty($topic)) wp_send_json_error(['message' => __('请输入主题', 'linked3-ai')]);

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
                } catch (\Throwable $e) {}
            }
            $content = self::generate_content($topic, $keywords, [], $tone, $word_count);
            $checked = self::self_check_content($content);

            wp_send_json_success([
                'content' => $checked,
                'word_count' => mb_strlen($checked),
                'checked' => true,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('内容生成失败: ', 'linked3-ai') . $e->getMessage()]);
        }
    }

    /**
     * v10.7.0 云模版保存 — 保存到wp_options, 跨生态共享
     */
    public static function ajax_template_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $template_json = wp_unslash($_POST['template'] ?? '');
        $template = json_decode($template_json, true);
        if (!is_array($template)) wp_send_json_error(['message' => __('模版数据格式错误', 'linked3-ai')]);

        $name = sanitize_text_field($template['name'] ?? '');
        $type = sanitize_key($template['type'] ?? 'content');
        if (empty($name)) wp_send_json_error(['message' => __('模版名称不能为空', 'linked3-ai')]);

        // v11.3.3: 接收 fork_id (派生模版的源模版ID, 可选)
        $fork_id = sanitize_text_field($_POST['fork_id'] ?? ($template['fork_id'] ?? ''));

        // 安全清洗10字段
        $config = [
            'profile'   => sanitize_textarea_field($template['config']['profile'] ?? ''),
            'role'      => sanitize_textarea_field($template['config']['role'] ?? ''),
            'scene'     => sanitize_textarea_field($template['config']['scene'] ?? ''),
            'background'=> sanitize_textarea_field($template['config']['background'] ?? ''),
            'goals'     => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['goals'] ?? ''))),
            'skills'    => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['skills'] ?? ''))),
            'style'     => sanitize_text_field($template['config']['style'] ?? ''),
            'limit'     => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['limit'] ?? ''))),
            'step'      => array_filter(array_map('sanitize_text_field', explode(',', $template['config']['step'] ?? ''))),
            'output'    => sanitize_textarea_field($template['config']['output'] ?? ''),
        ];

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
            'message'     => __('模版已保存并加入跨生态共享池', 'linked3-ai'),
        ]);
    }

    /**
     * v10.7.0 图片设置保存 — 保存到wp_options
     */
    public static function ajax_image_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $settings_json = wp_unslash($_POST['settings'] ?? '');
        $settings = json_decode($settings_json, true);
        if (!is_array($settings)) wp_send_json_error(['message' => __('设置数据格式错误', 'linked3-ai')]);

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
            'message'  => __('图片设置已保存', 'linked3-ai'),
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
            error_log('[linked3 eco_save_draft] 权限不足, user_id=' . get_current_user_id());
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) {
            error_log('[linked3 eco_save_draft] nonce验证失败');
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_unslash($_POST['content'] ?? '');

        // R5修复: 空内容兜底 — 返回明确错误而非让wp_insert_post静默失败
        if (empty($title)) {
            error_log('[linked3 eco_save_draft] 标题为空');
            wp_send_json_error(['message' => __('标题不能为空', 'linked3-ai')]);
        }
        if (empty($content)) {
            error_log('[linked3 eco_save_draft] 内容为空, title=' . $title);
            wp_send_json_error(['message' => __('内容不能为空, 请先完成内容生成步骤', 'linked3-ai')]);
        }

        // R5修复: 内容长度检查 (WordPress wp_insert_post 对极短内容可能失败)
        $content_plain = wp_strip_all_tags($content);
        $content_plain = trim(preg_replace('/\s+/', ' ', $content_plain));
        if (strlen($content_plain) < 10) {
            error_log('[linked3 eco_save_draft] 内容过短 (' . strlen($content_plain) . ' chars), title=' . $title);
            wp_send_json_error(['message' => __('内容过短, 请检查内容生成是否完整', 'linked3-ai')]);
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'post',
        ], true);

        if (is_wp_error($post_id)) {
            error_log('[linked3 eco_save_draft] wp_insert_post失败: ' . $post_id->get_error_message() . ', title=' . $title);
            wp_send_json_error(['message' => __('保存失败: ', 'linked3-ai') . $post_id->get_error_message()]);
        }

        error_log('[linked3 eco_save_draft] 成功, post_id=' . $post_id . ', title=' . $title . ', content_len=' . strlen($content));

        wp_send_json_success([
            'post_id'  => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'message'  => __('已保存为草稿, ID: ', 'linked3-ai') . $post_id,
        ]);
    }

    // ================================================================
    // v10.7.4 图片API保存 + 图片生成
    // ================================================================

    /**
     * v10.7.4 保存图片生成API设置
     */
    public static function ajax_save_image_api() : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $provider = sanitize_key($_POST['provider'] ?? 'siliconflow');
        $model = sanitize_text_field($_POST['model'] ?? 'Kwai-Kolors/Kolors');
        $api_base = esc_url_raw($_POST['api_base'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $width = max(256, min(4096, intval($_POST['width'] ?? 1024)));
        $height = max(256, min(4096, intval($_POST['height'] ?? 1024)));

        update_option(LINKED3_OPTION_PREFIX . 'image_provider', $provider);
        update_option(LINKED3_OPTION_PREFIX . 'image_model', $model);
        update_option(LINKED3_OPTION_PREFIX . 'image_api_base', $api_base);
        update_option(LINKED3_OPTION_PREFIX . 'image_api_key', $api_key);
        update_option(LINKED3_OPTION_PREFIX . 'image_width', $width);
        update_option(LINKED3_OPTION_PREFIX . 'image_height', $height);

        wp_send_json_success(['message' => __('图片API设置已保存', 'linked3-ai')]);
    }

    /**
     * v10.7.4 实际生成图片 — SOP闭环下一步
     * 接收图片Prompt列表, 调用AI图片API生成实际图片
     */
        public static function ajax_generate_images() { return EcosystemAjaxAdvanced::ajax_generate_images(); }

    // ================================================================
    // v10.7.1 全功能链新增方法
    // ================================================================

    /**
     * v10.7.1 热词采集 — 多源采集 (百度/搜狗/360/知乎/微博/抖音)
     */
        public static function ajax_hot_collect() { return EcosystemAjaxAdvanced::ajax_hot_collect(); }

    /**
     * v10.7.1 关键词库保存 (热词库/长尾词库)
     */
    public static function ajax_keywords_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $type = sanitize_key($_POST['type'] ?? 'hot');
        $keywords_raw = wp_unslash($_POST['keywords'] ?? '');
        $keywords = array_filter(array_map('sanitize_text_field', explode("\n", $keywords_raw)));
        $keywords = array_slice($keywords, 0, 500);

        $option_key = LINKED3_OPTION_PREFIX . ($type === 'tail' ? 'tail_keywords' : 'hot_keywords');
        update_option($option_key, $keywords, false);

        wp_send_json_success([
            'type' => $type,
            'count' => count($keywords),
            'message' => ($type === 'tail' ? '长尾词库' : '热词库') . '已保存: ' . count($keywords) . '个',
        ]);
    }

    /**
     * v16.0.14 [公理α: H↓ 消除"用过没"不确定性] [公理β: dim↓ 0维自动持久化]
     * 长尾词使用状态保存 — 记录哪些长尾词已用于生成文章
     * 数据格式: {keyword: 1, ...} 存入 option, SQLite 兼容 (v16.0.1 约束)
     */
    public static function ajax_tail_used_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $used_map_raw = wp_unslash($_POST['used_map'] ?? '{}');
        $used_map = json_decode($used_map_raw, true);
        if (!is_array($used_map)) {
            $used_map = [];
        }

        // 清洗: 只保留 keyword => 1 的键值对, 键名 sanitize
        $clean = [];
        foreach ($used_map as $kw => $val) {
            $kw_clean = sanitize_text_field($kw);
            if ($kw_clean !== '') {
                $clean[$kw_clean] = 1;
            }
        }
        // 限流: 最多 2000 条, 防止 option 膨胀
        if (count($clean) > 2000) {
            $clean = array_slice($clean, -2000, null, true);
        }

        update_option(LINKED3_OPTION_PREFIX . 'tail_keywords_used', $clean, false);

        wp_send_json_success([
            'count' => count($clean),
            'message' => __('使用状态已保存: ', 'linked3-ai') . count($clean) . '个已用',
        ]);
    }

    /**
     * v10.7.1 长文写作 — 生成大纲
     */
        public static function ajax_longform_outline() { return EcosystemAjaxAdvanced::ajax_longform_outline(); }

    /**
     * v10.7.1 长文写作 — 生成单段
     */
        public static function ajax_longform_section() { return EcosystemAjaxAdvanced::ajax_longform_section(); }

    /**
     * v10.7.1 CSV批量写作
     */
        public static function ajax_csv_batch() { return EcosystemAjaxAdvanced::ajax_csv_batch(); }

    /**
     * v10.7.1 定时任务启用
     */
    public static function ajax_cron_enable() : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $freq = sanitize_key($_POST['freq'] ?? 'daily');
        $count = max(1, min(20, intval($_POST['count'] ?? 3)));

        // 注册定时任务
        $hook = 'linked3_eco_cron_auto_generate';
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $freq, $hook, [$count]);
        }
        update_option(LINKED3_OPTION_PREFIX . 'cron_settings', [
            'enabled' => true,
            'freq' => $freq,
            'count' => $count,
            'updated_at' => current_time('mysql'),
        ], false);

        wp_send_json_success([
            'message' => __('定时任务已启用: ', 'linked3-ai') . $freq . ' 生成' . $count . '篇',
            'freq' => $freq,
            'count' => $count,
        ]);
    }

    /**
     * v10.7.1 定时任务禁用
     */
    public static function ajax_cron_disable() : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);

        $hook = 'linked3_eco_cron_auto_generate';
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        update_option(LINKED3_OPTION_PREFIX . 'cron_settings', ['enabled' => false], false);

        wp_send_json_success(['message' => __('定时任务已禁用', 'linked3-ai')]);
    }

    // ================================================================
    // 内部方法
    // ================================================================

    private static function generate_keywords(string $seed, int $count = 20): array {
        // 委托 Keyword_Manager (若存在)
        if (class_exists('\Linked3\Classes\Content\Linked3_Keyword_Manager')) {
            try {
                $mgr = new \Linked3_Keyword_Manager();
                if (method_exists($mgr, 'generate_tail_keywords')) {
                    $result = $mgr->generate_tail_keywords($seed, $count);
                    if (is_array($result) && !empty($result)) return $result;
                }
            } catch (\Throwable $e) {}
        }

        // 降级: 本地生成
        $keywords = [$seed];
        $templates = [
            '%s是什么', '%s怎么做', '%s教程', '%s攻略', '%s工具',
            '%s软件', '%s推荐', '%s对比', '最好的%s', '免费的%s',
            '%s2026', '%s最新', '%s入门', '%s进阶', '%s实战',
            '%s案例', '%s技巧', '%s方法', '%s指南', '%s大全',
        ];
        foreach ($templates as $tpl) {
            if (count($keywords) >= $count) break;
            $keywords[] = sprintf($tpl, $seed);
        }
        return array_slice(array_unique($keywords), 0, $count);
    }

    private static function classify_keywords(array $keywords): array {
        $classified = ['primary' => [], 'long_tail' => [], 'question' => []];
        foreach ($keywords as $kw) {
            if (mb_strpos($kw, '什么') !== false || mb_strpos($kw, '怎么') !== false) {
                $classified['question'][] = $kw;
            } elseif (mb_strlen($kw) > 8) {
                $classified['long_tail'][] = $kw;
            } else {
                $classified['primary'][] = $kw;
            }
        }
        return $classified;
    }

    private static function load_template(string $category): array {
        // v10.7.0: 优先从跨生态共享池加载
        $shared_templates = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);
        foreach ($shared_templates as $tpl) {
            if (($tpl['type'] ?? '') === $category) return $tpl;
        }

        // 委托 Template_Manager (若存在)
        if (class_exists('\\Linked3\\Classes\\Templates\\TemplateManager')) {
            try {
                $mgr = new \Linked3\Classes\Templates\TemplateManager();
                $templates = $mgr->get_by_category($category);
                if (!empty($templates)) return $templates[0];
            } catch (\Throwable $e) {}
        }

        // v10.7.0: 委托 Cloud_Template_Factory (若存在)
        if (class_exists('\Linked3\Classes\Content\CloudTemplateFactory')) {
            try {
                $factory = new \CloudTemplateFactory();
                if (method_exists($factory, 'load_template_by_category')) {
                    $tpl = $factory->load_template_by_category($category);
                    if (!empty($tpl)) return $tpl;
                }
            } catch (\Throwable $e) {}
        }

        return ['name' => $category . '_default', 'type' => $category];
    }

    private static function generate_content(string $topic, array $keywords, array $template = [], string $tone = 'professional', int $word_count = 800): string {
        // 委托 Long_Form_Writer (若存在)
        if (class_exists('\Linked3\Classes\Content\LongFormWriter')) {
            try {
                $writer = new \LongFormWriter();
                if (method_exists($writer, 'generate')) {
                    $result = $writer->generate($topic, implode(',', $keywords), ['word_count' => $word_count, 'tone' => $tone]);
                    if (is_string($result) && !empty($result)) return $result;
                }
            } catch (\Throwable $e) {}
        }

        // v11.0.9 #4: 用模版提示词调用AI生成真实文章 (绞杀假大空降级内容)
        $cfg = $template['config'] ?? $template;
        $prompt_parts = [];
        if (!empty($cfg['role'])) $prompt_parts[] = '你的角色: ' . $cfg['role'];
        if (!empty($cfg['scene'])) $prompt_parts[] = '适用场景: ' . $cfg['scene'];
        if (!empty($cfg['background'])) $prompt_parts[] = '背景: ' . $cfg['background'];
        $goals = $cfg['goals'] ?? [];
        if (is_array($goals) && !empty($goals)) $prompt_parts[] = '目标: ' . implode('、', $goals);
        $skills = $cfg['skills'] ?? [];
        if (is_array($skills) && !empty($skills)) $prompt_parts[] = '技能要求: ' . implode('、', $skills);
        if (!empty($cfg['style'])) $prompt_parts[] = '风格: ' . $cfg['style'];
        $limits = $cfg['limit'] ?? [];
        if (is_array($limits) && !empty($limits)) $prompt_parts[] = '限制: ' . implode('、', $limits);
        $steps = $cfg['step'] ?? [];
        if (is_array($steps) && !empty($steps)) $prompt_parts[] = '写作步骤: ' . implode(' → ', $steps);
        if (!empty($cfg['output'])) $prompt_parts[] = '输出格式: ' . $cfg['output'];

        $kw_str = implode('、', array_slice($keywords, 0, 8));
        $prompt = "请为主题「{$topic}」撰写一篇文章。\n\n";
        if (!empty($prompt_parts)) {
            $prompt .= "模版要求:\n" . implode("\n", $prompt_parts) . "\n\n";
        }
        $prompt .= "关键词: {$kw_str}\n";
        $prompt .= "字数: 约{$word_count}字\n";
        $prompt .= "语气: {$tone}\n\n";
        $prompt .= "严格要求:\n1. 内容具体、有信息量, 不要空话套话\n2. 不要使用「赋能/闭环/抓手/底层逻辑/范式/矩阵」等AI高频词\n3. 适合博客/公众号发布\n4. 直接输出正文, 不要说明\n";

        // v11.8.0: 贯彻全局格式变量 — require_html/require_tag/enable_ai_summary
        $adv_settings = wp_parse_args(
            (array) get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', []),
            ['require_html' => false, 'require_tag' => false, 'enable_ai_summary' => false]
        );
        if (class_exists('\Linked3\Classes\Content\AIEnhancer')) {
            try {
                $enhancer = new \AIEnhancer();
                $prompt = $enhancer->apply_format_requirements($prompt, $adv_settings);
            } catch (\Throwable $e) {}
        } elseif (!empty($adv_settings['require_html'])) {
            // 降级: 直接追加HTML格式要求
            $prompt .= "\n返回的文章内容必须用 HTML 标签格式,不要加 CSS 代码,不需要 <!DOCTYPE html>、<html>、<head>、<body> 标签。文章标题用 H1 标签。";
        }

        $ai_content = self::call_ai($prompt, max(1000, intval($word_count * 1.5)));
        if (!empty($ai_content)) {
            // v11.8.0: 若require_html但AI仍返回Markdown, 用转换器降级处理
            if (!empty($adv_settings['require_html'])
                && class_exists('\Linked3\Classes\Content\MarkdownHtmlConverter')
                && strpos($ai_content, '<') === false) {
                try {
                    $ai_content = \MarkdownHtmlConverter::convert($ai_content);
                } catch (\Throwable $e) {}
            }
            // v11.8.0: 追加AI标识符后缀(全局设置)
            if (class_exists('\Linked3\Classes\Content\AIEnhancer')) {
                try {
                    $ai_content = (new \AIEnhancer())->append_identifier_suffix($ai_content);
                } catch (\Throwable $e) {}
            }
            return self::self_check_content($ai_content);
        }

        // 最终降级: AI不可用时返回空字符串 (不返回假大空模板字符串)
        // 调用方应检查空值并报错
        return '';
    }

    /**
     * v10.7.5: feicai4.0 21条AI痕迹识别 — 去AI味自检
     * 参考 feicai4.0 zh-humanizer/SKILL.md
     */
    private static function self_check_content(string $content): string {
        // 1. 移除过度承诺词
        $overclaims = ['最好', '第一', '唯一', '100%', '绝对', '完美', '无敌', '顶级', '极致'];
        foreach ($overclaims as $word) {
            $content = str_replace($word, '优秀', $content);
        }

        // 2. 移除意义膨胀句
        $inflations = [
            '标志着…新时代' => '',
            '从更宏观层面看' => '',
            '具有重大意义' => '很重要',
            '产生深远影响' => '影响很大',
        ];
        foreach ($inflations as $from => $to) {
            $content = str_replace($from, $to, $content);
        }

        // 3. 移除伪深度动词
        $pseudoVerbs = [
            '提升…能力' => '改善',
            '促进…发展' => '推动',
            '推动…进程' => '推进',
            '赋能…' => '支持',
        ];
        foreach ($pseudoVerbs as $from => $to) {
            $content = preg_replace('/' . preg_quote($from, '/') . '/u', $to, $content);
        }

        // 4. 移除广告宣传语气
        $ads = ['卓越', '一站式', '全方位', '极致体验'];
        foreach ($ads as $word) {
            $content = str_replace($word, '全面', $content);
        }

        // 5. 移除AI高频词
        $aiWords = ['赋能', '闭环', '抓手', '底层逻辑', '范式', '矩阵'];
        foreach ($aiWords as $word) {
            $content = str_replace($word, '', $content);
        }

        // 6. 移除空洞结尾
        $emptyEndings = ['未来可期', '值得期待', '前景广阔'];
        foreach ($emptyEndings as $word) {
            $content = str_replace($word, '建议立即行动', $content);
        }

        return $content;
    }

    /**
     * v10.7.5: feicai4.0叙事式图片Prompt — 用完整段落描述
     * 参考 feicai4.0 gemini-image-prompt-guide.md
     */
    private static function generate_images(string $content, array $keywords): array {
        $images = [];
        $kw_str = implode('、', array_slice($keywords, 0, 3));
        $primary_kw = $keywords[0] ?? '内容';

        // v10.7.5: 叙事式Prompt (非关键词堆叠)
        $types = ['featured', 'content_1', 'content_2'];
        $prompts = [
            'featured' => sprintf(
                '中景，专业信息图设计。画面中央展示「%s」的核心概念，用简洁的图标和流程图呈现%s的关键要素。背景使用浅蓝色渐变，配以白色文字标签。整体风格专业、清晰，适合技术博客封面。画面中不包含任何水印。',
                $primary_kw, $kw_str
            ),
            'content_1' => sprintf(
                '中景，信息图。以「%s」为主题，用三栏对比布局展示核心要点。左侧是问题场景，中间是解决方案，右侧是预期效果。配色采用蓝白灰三色系，文字标签清晰可读。画面中不包含任何水印。',
                $primary_kw
            ),
            'content_2' => sprintf(
                '中景，流程信息图。以「%s」为核心，用箭头流程图展示从输入到输出的完整路径。每个步骤配有简洁图标和关键词标签：%s。背景为浅灰色，强调内容层次感。画面中不包含任何水印。',
                $primary_kw, $kw_str
            ),
        ];

        foreach ($types as $type) {
            $images[] = [
                'type' => $type,
                'prompt' => $prompts[$type],
                'resolution' => '1280*1280',
                'layout' => 'list',
            ];
        }
        return $images;
    }

    /**
     * v10.7.5: feicai4.0准确性检查清单 — 质检增强
     * 参考 feicai4.0 accuracy-checklist.md
     */
    private static function quality_check(array $keywords, array $template, string $content, array $images): array {
        $score = 0;
        $checks = [];

        // 基础检查 (4项, 各20分)
        $checks['keywords'] = !empty($keywords);
        if ($checks['keywords']) $score += 20;

        $checks['template'] = !empty($template);
        if ($checks['template']) $score += 20;

        $checks['content'] = !empty($content) && mb_strlen($content) > 100;
        if ($checks['content']) $score += 20;

        $checks['images'] = !empty($images);
        if ($checks['images']) $score += 20;

        // v10.7.5: feicai4.0深度质检 (2项, 各10分)
        // 逻辑性检查: 内容是否有结构化标题
        $checks['logical_structure'] = preg_match('/^##\s/m', $content);
        if ($checks['logical_structure']) $score += 10;

        // AI痕迹检查: 是否还残留AI高频词
        $aiResidue = ['赋能', '闭环', '抓手', '底层逻辑', '范式', '矩阵', '未来可期'];
        $hasAiResidue = false;
        foreach ($aiResidue as $word) {
            if (mb_strpos($content, $word) !== false) {
                $hasAiResidue = true;
                break;
            }
        }
        $checks['no_ai_traces'] = !$hasAiResidue;
        if ($checks['no_ai_traces']) $score += 10;

        return [
            'score' => $score,
            'checks' => $checks,
            'passed' => $score >= 60,
        ];
    }

    /**
     * v10.9.0 AI调用统一辅助方法 — 绞杀假大空内容
     * 调用 AI_Dispatcher 生成真实内容, 失败时返回空字符串 (不返回假内容)
     *
     * @param string $prompt  完整提示词
     * @param int    $max_tokens 最大token数
     * @return string AI生成内容 (空字符串=调用失败, 调用方应报错而非返回假内容)
     */
    private static function call_ai(string $prompt, int $max_tokens = 2000): string {
        return self::call_ai_internal($prompt, $max_tokens);
    }

    public static function call_ai_internal(string $prompt, int $max_tokens = 2000): string {
        if (!class_exists('\\Linked3\\Classes\\Core\\AIDispatcher')) {
            return '';
        }
        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            $messages = [['role' => 'user', 'content' => $prompt]];
            $options = ['max_tokens' => $max_tokens, 'temperature' => 0.7];
            $config = [];
            $result = $dispatcher->chat($messages, $options, $config);
            return $result['content'] ?? '';
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[linked3 v10.9.0] AI call failed: ' . $e->getMessage());
            }
            return '';
        }
    }
}

add_action('init', ['\Linked3\Classes\Content\EcosystemAjax', 'register'], 5);
