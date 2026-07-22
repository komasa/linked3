<?php

declare(strict_types=1);
/**
 * Dashboard AJAX Registrar (v4.3.9 split).
 *
 * Owns every `wp_ajax_linked3_*` handler that was previously inlined in
 * the 951-line Dashboard_Hooks_Registrar god class. Each handler enforces
 * the standard triple-gate: capability check + nonce verification + input
 * sanitization (delegated to the appropriate sanitize_* helper).
 *
 * Registration is split from the menu/render code so AJAX endpoints can be
 * audited, tested and refactored without touching the admin UI.
 *
 * @package Linked3
 * @subpackage Classes\Dashboard
 */

namespace Linked3\Classes\Dashboard;

use Linked3\Classes\Templates\TemplateManager;
use Linked3\Classes\SEO\Keyword\KeywordManager;
use Linked3\Classes\Core\AIDispatcher;
use Linked3\Includes\Http\SafeRemote;



if (!defined('ABSPATH')) {
    exit;
}
final class DashboardAjaxRegistrarLegacy
{
    /**
     * Register every AJAX action owned by the Dashboard module.
     *
     * Called by DashboardHooksRegistrar::register() — do not
     * call directly.
     *
     * @return void
     */
    static function register(): void {
        // 自定义 API 保存 AJAX
        add_action('wp_ajax_linked3_save_custom_apis', [__CLASS__, 'ajax_save_custom_apis']);
        // 模板 CRUD AJAX
        // G2.1: Template actions migrated to DashboardTemplateActions
        // (self-registered via DashboardAjaxRegistrar::register())
        // add_action('wp_ajax_linked3_template_add', [__CLASS__, 'ajax_template_add']);
        // add_action('wp_ajax_linked3_template_update', [__CLASS__, 'ajax_template_update']);
        // add_action('wp_ajax_linked3_template_delete', [__CLASS__, 'ajax_template_delete']);
        // add_action('wp_ajax_linked3_template_get', [__CLASS__, 'ajax_template_get']);
        // 模型同步 AJAX
        add_action('wp_ajax_linked3_sync_models', [__CLASS__, 'ajax_sync_models']);
        // AI 标识符后缀
        add_action('wp_ajax_linked3_save_ai_suffix', [__CLASS__, 'ajax_save_ai_suffix']);
        // 关键词管理 (热词采集/长尾生成/批量文章)
        // G2.1: Keyword actions migrated to DashboardKeywordActions (delegate methods removed)
        // 高级设置 (原版隐藏功能)
        add_action('wp_ajax_linked3_save_advanced', [__CLASS__, 'ajax_save_advanced']);
        // 图片设置 (原版 image_settings)
        add_action('wp_ajax_linked3_save_image_settings', [__CLASS__, 'ajax_save_image_settings']);
        // v5.3.3: 图片站采集测试 + 图示提示词生成
        add_action('wp_ajax_linked3_test_image_station', [__CLASS__, 'ajax_test_image_station']);
        add_action('wp_ajax_linked3_generate_chart_prompts', [__CLASS__, 'ajax_generate_chart_prompts']);
        // v5.3.4: 视频脚本 + 图示脚本分段生成 (先大纲后分镜)
        add_action('wp_ajax_linked3_video_outline', [__CLASS__, 'ajax_video_outline']);
        add_action('wp_ajax_linked3_video_segment', [__CLASS__, 'ajax_video_segment']);
        add_action('wp_ajax_linked3_chart_outline', [__CLASS__, 'ajax_chart_outline']);
        add_action('wp_ajax_linked3_chart_segment', [__CLASS__, 'ajax_chart_segment']);
        // v6.5.0: 图示脚本引擎 — 接入 Diagram Master Template
        add_action('wp_ajax_linked3_diagram_generate', [__CLASS__, 'ajax_diagram_generate']);
        add_action('wp_ajax_linked3_diagram_validate', [__CLASS__, 'ajax_diagram_validate']);
        add_action('wp_ajax_linked3_diagram_types', [__CLASS__, 'ajax_diagram_types']);
        // v6.5.4: 多图示提示词分开生成 (每模块1个独立Prompt)
        add_action('wp_ajax_linked3_diagram_generate_multi', [__CLASS__, 'ajax_diagram_generate_multi']);
        // v6.6.0 ~ v9.1.4: Genesis 漫画脚本引擎 — 15 个 AJAX 端点
        // 已在 v27.1.0 (P10) 拆分到 DashboardAjaxGenesis 独立类,
        // 此处仅做转发注册, 保持向后兼容 (handler 实现仍在本类中, 待 step 2 迁移).
        \Linked3\Classes\Dashboard\Ajax\DashboardAjaxGenesis::register();
        // v7.1.5: WP-Cron 回调
        add_action('linked3_genesis_run_job', [__CLASS__, 'cron_genesis_run_job']);
        // G2.1: Queue actions migrated to DashboardQueueActions
        // add_action('wp_ajax_linked3_queue_list', [__CLASS__, 'ajax_queue_list']);
        // add_action('wp_ajax_linked3_queue_retry', [__CLASS__, 'ajax_queue_retry']);
        // add_action('wp_ajax_linked3_queue_delete', [__CLASS__, 'ajax_queue_delete']);
        // add_action('wp_ajax_linked3_queue_bulk_delete', [__CLASS__, 'ajax_queue_bulk_delete']);
        // v3.1.0: Provider 配置 AJAX 保存 (不刷新页面)
        add_action('wp_ajax_linked3_save_provider_config', [__CLASS__, 'ajax_save_provider_config']);
        // v3.1.0: SEO 增强 (内链/Schema/外链) 保存
        add_action('wp_ajax_linked3_save_seo_enhance', [__CLASS__, 'ajax_save_seo_enhance']);
        // v3.2.0: 图片模型同步
        add_action('wp_ajax_linked3_sync_image_models', [__CLASS__, 'ajax_sync_image_models']);
        // v3.2.0: 视频脚本生成 (调用 Video_Generator)
        add_action('wp_ajax_linked3_video_generate_script', [__CLASS__, 'ajax_video_generate_script']);
        // v3.3.0: 长文分段写作 (大纲生成 + 逐段生成)
        add_action('wp_ajax_linked3_generate_outline', [__CLASS__, 'ajax_generate_outline']);
        add_action('wp_ajax_linked3_generate_section', [__CLASS__, 'ajax_generate_section']);
        // v3.4.0: GEO 增强 (保存设置 + 重新生成 llms.txt)
        add_action('wp_ajax_linked3_save_geo', [__CLASS__, 'ajax_save_geo']);
        add_action('wp_ajax_linked3_regen_llms_txt', [__CLASS__, 'ajax_regen_llms_txt']);
        // v3.7.0: AI 搜索引擎 API key (放在 API 设置页)
        add_action('wp_ajax_linked3_save_ai_search_keys', [__CLASS__, 'ajax_save_ai_search_keys']);
        // v5.2.4: 关键词库保存 + 定时任务管理
        add_action('wp_ajax_linked3_kw_save_library', [__CLASS__, 'ajax_kw_save_library']);
        add_action('wp_ajax_linked3_kw_cron_enable', [__CLASS__, 'ajax_kw_cron_enable']);
        add_action('wp_ajax_linked3_kw_cron_disable', [__CLASS__, 'ajax_kw_cron_disable']);
        add_action('wp_ajax_linked3_kw_cron_status', [__CLASS__, 'ajax_kw_cron_status']);
    }

    /**
     * AJAX: 同步 Provider 的最新模型列表 (调用 /models 接口)。
     */

    /**
     * AJAX: 保存 AI 标识符后缀 (AI 生成内容自动追加的免责声明)。
     */

    /**
     * AJAX: 保存高级设置 (原版隐藏功能)。
     */
    static function ajax_save_advanced(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $enhancer = new \Linked3\Classes\Core\AIEnhancer();
        $input = [
            'require_html' => !empty($_POST['require_html']),
            'require_tag' => !empty($_POST['require_tag']),
            'auto_generate' => !empty($_POST['auto_generate']),
            'provider' => sanitize_text_field($_POST['provider'] ?? 'openai'),
            'model' => sanitize_text_field($_POST['model'] ?? 'dall-e-3'),
            'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
            'api_url' => esc_url_raw($_POST['api_url'] ?? ''),
            'img_width' => (int) ($_POST['img_width'] ?? 800),
            'img_height' => (int) ($_POST['img_height'] ?? 600),
            'insert_position' => sanitize_text_field($_POST['insert_position'] ?? 'after_first_h2'),
            'image_count' => (int) ($_POST['image_count'] ?? 1),
            'image_alignment' => sanitize_text_field($_POST['image_alignment'] ?? 'center'),
            'prompt_source' => sanitize_text_field($_POST['prompt_source'] ?? 'title'),
            'custom_prompt' => sanitize_textarea_field($_POST['custom_prompt'] ?? ''),
            'save_to_media' => !empty($_POST['save_to_media']),
            'station_url' => esc_url_raw($_POST['station_url'] ?? ''),
            'station_count' => (int) ($_POST['station_count'] ?? 1),
            'image_station_key' => sanitize_text_field($_POST['image_station_key'] ?? ''),
        ];
        $enhancer->save_settings($input);
        wp_send_json_success(['saved' => true]);
    }

    /**
     * AJAX: 保存自定义 API 配置
     */
    static function ajax_save_custom_apis(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $apis_json = wp_unslash($_POST['apis'] ?? '{}');
        $apis = json_decode($apis_json, true);
        if (!is_array($apis)) {
            wp_send_json_error(['message' => __('无效数据', 'linked3')], 400);
        }
        $clean = [];
        foreach ($apis as $id => $api) {
            $id = sanitize_key($id);
            $clean[$id] = [
                'name' => sanitize_text_field($api['name'] ?? ''),
                'url' => esc_url_raw(trim($api['url'] ?? '')),
                'model' => sanitize_text_field($api['model'] ?? ''),
                'key' => sanitize_textarea_field($api['key'] ?? ''),
            ];
        }
        update_option(LINKED3_OPTION_PREFIX . 'custom_apis', $clean);
        wp_send_json_success(['saved' => count($clean)]);
    }

    /**
     * v3.1.0: AJAX 保存 Provider 配置 (不刷新页面)
     *
     * 接收字段:
     *   - linked3_default_provider
     *   - linked3_key_rotation
     *   - linked3_provider_api_bases[slug] = base_url
     *   - linked3_provider_models[slug] = model
     *   - linked3_provider_keys[slug] = keys (textarea, 换行分隔)
     */
    static function ajax_save_provider_config(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden'], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $models = $_POST['provider_models'] ?? [];
        if (is_array($models)) {
            $clean_models = [];
            foreach ($models as $slug => $model) {
                $clean_models[sanitize_key($slug)] = sanitize_text_field($model);
            }
            update_option(LINKED3_OPTION_PREFIX . 'provider_models', $clean_models);
        }

        // provider_keys (数组,textarea 多行)
        $keys = $_POST['provider_keys'] ?? [];
        $saved_keys_count = 0;
        if (is_array($keys)) {
            $clean_keys = [];
            foreach ($keys as $slug => $raw_keys) {
                $slug_clean = sanitize_key($slug);
                // 保留换行,只做 textarea sanitize
                $clean_keys[$slug_clean] = sanitize_textarea_field($raw_keys);
                if (!empty(trim($clean_keys[$slug_clean]))) {
                    $saved_keys_count++;
                }
            }
            update_option(LINKED3_OPTION_PREFIX . 'provider_keys', $clean_keys);
        }

        // v3.1.1: 返回保存的 key 数量,方便用户验证
        wp_send_json_success([
            'saved' => true,
            'message' => sprintf('Provider 配置已保存 (%d 个 provider 有 key)', $saved_keys_count),
            'saved_keys_count' => $saved_keys_count,
            'default_provider' => $default_provider,
        ]);
    }

    /**
     * v3.1.0: AJAX 保存 SEO 增强 (内链/Schema/外链)
     */
    static function ajax_save_seo_enhance(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        $interlink_enabled = !empty($_POST['interlink_enabled']);
        $schema_enabled    = !empty($_POST['schema_enabled']);
        $external_enabled  = !empty($_POST['external_enabled']);
        update_option(LINKED3_OPTION_PREFIX . 'seo_interlink_enabled', $interlink_enabled);
        update_option(LINKED3_OPTION_PREFIX . 'seo_schema_enabled', $schema_enabled);
        update_option(LINKED3_OPTION_PREFIX . 'seo_external_enabled', $external_enabled);
        wp_send_json_success(['saved' => true, 'message' => 'SEO saved']);
    }

    /**
     * AJAX: 同步图片模型列表
     */
    public static function ajax_sync_image_models() : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        // Get models via ProviderFactory (AIDispatcher doesn't have get_models())
        $provider_slug = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'openai');
        $api_key = get_option(LINKED3_OPTION_PREFIX . 'api_key_' . $provider_slug, '');
        $api_base = get_option(LINKED3_OPTION_PREFIX . 'api_base_' . $provider_slug, '');

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'No API key configured for provider: ' . $provider_slug]);
        }

        $factory = \Linked3\Classes\Core\Providers\ProviderFactory::instance();
        $provider = $factory->make($provider_slug);
        if (!$provider) {
            wp_send_json_error(['message' => 'Provider not registered: ' . $provider_slug]);
        }

        $all_models = $provider->get_models([
            'api_key' => $api_key,
            'api_base' => $api_base,
        ]);
        if (is_wp_error($all_models) || empty($all_models)) {
            wp_send_json_error(['message' => 'Failed to get models']);
        }
        $img_models = [];
        foreach ($all_models as $m) {
            $id = $m['id'] ?? '';
            if (preg_match('/.*(dall-e|gpt-image|flux|stable-diffusion|sdxl|imagen|midjourney|colors|seedream)/i', $id)) {
                $img_models[] = $id;
            }
        }
        if (empty($img_models)) {
            $img_models = array_map(function($m) { return $m['id'] ?? ''; }, $all_models);
        }
        $img_models = array_filter($img_models);
        sort($img_models);
        wp_send_json_success([
            'models' => array_slice($img_models, 0, 50),
            'count' => count($img_models),
            'total_available' => count($all_models),
        ]);
    }
}
