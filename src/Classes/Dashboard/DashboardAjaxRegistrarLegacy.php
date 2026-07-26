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

use Linked3\Includes\Log\Logger;

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
        // v28.0.0: Permanently disabled — all handlers migrated to Actions classes.
        // Calling register() would create 41 duplicate wp_ajax registrations.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            Logger::instance()->warning('general', '[linked3] DashboardAjaxRegistrarLegacy::register() — dead code (v28.0.0)');
        }
        return;

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
            'message' => sprintf(__('Provider 配置已保存 (%d 个 provider 有 key)', 'linked3'), $saved_keys_count),
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
