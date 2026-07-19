<?php

declare(strict_types=1);
/**
 * Dashboard Menu Registrar (v4.3.9 split).
 *
 * Owns the admin menu registration, the dashboard render callback, the
 * WordPress settings registration (register_setting + sanitize callbacks)
 * and the license-key option hook.
 *
 * Split from the 951-line Dashboard_Hooks_Registrar so menu/render code can
 * evolve without touching AJAX handlers.
 *
 * @package Linked3
 * @subpackage Classes\Dashboard
 */

namespace Linked3\Classes\Dashboard;

if (!defined('ABSPATH')) {
    exit;
}

final class DashboardMenuRegistrar
{
    /**
     * Register the admin menu + WP settings + license hook.
     *
     * Called by DashboardHooksRegistrar::register() — do not
     * call directly.
     *
     * @return void
     */
    public static function register()
    : void {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    /**
     * Register WP settings so options.php accepts our form POSTs.
     *
     * Note: the `wp_ajax_linked3_*` action registrations that used to live
     * here have moved to DashboardAjaxRegistrar::register() in
     * v4.3.9. This method now only owns register_setting() calls + the
     * license-key option hook (which is a settings lifecycle concern, not
     * an AJAX endpoint).
     */
    public static function register_settings()
    : void {
        register_setting('linked3_api_settings', LINKED3_OPTION_PREFIX . 'provider_keys', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_provider_keys'],
            'default' => [],
        ]);
        register_setting('linked3_api_settings', LINKED3_OPTION_PREFIX . 'provider_api_bases', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_api_bases'],
            'default' => [],
        ]);
        register_setting('linked3_api_settings', LINKED3_OPTION_PREFIX . 'provider_models', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_models'],
            'default' => [],
        ]);
        register_setting('linked3_api_settings', 'linked3_default_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'openai',
        ]);
        register_setting('linked3_api_settings', 'linked3_key_rotation', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'disabled',
        ]);
        register_setting('linked3_license_settings', LINKED3_OPTION_PREFIX . 'license_key_input', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        // When license_key_input is saved via options.php, sync the key into
        // the License_Service so heartbeat/validation can use it.
        add_action('update_option_' . LINKED3_OPTION_PREFIX . 'license_key_input', [__CLASS__, 'on_license_saved'], 10, 3);
    }

    /**
     * Sanitize provider keys — 支持多 Key (换行分隔)。
     */
    public static function sanitize_provider_keys(mixed $input) : mixed {
        if (!is_array($input)) return [];
        $out = [];
        $allowed = ['openai', 'deepseek', 'kimi', 'qwen', 'doubao', 'zhipu', 'zai', 'siliconflow', 'hunyuan', 'tencent_lke'];
        foreach ($allowed as $slug) {
            if (!empty($input[$slug])) {
                $keys = array_filter(array_map('trim', explode("\n", $input[$slug])));
                $out[$slug] = implode("\n", $keys);
            }
        }
        return $out;
    }

    /**
     * Sanitize API base URLs.
     */
    public static function sanitize_api_bases(mixed $input) : mixed     {
        if (!is_array($input)) return [];
        $out = [];
        foreach ($input as $slug => $url) {
            $url = trim((string) $url);
            if ($url) {
                $out[sanitize_key($slug)] = esc_url_raw(rtrim($url, '/'));
            }
        }
        return $out;
    }

    /**
     * Sanitize model names.
     */
    public static function sanitize_models(mixed $input) : mixed {
        if (!is_array($input)) return [];
        $out = [];
        foreach ($input as $slug => $model) {
            $model = trim((string) $model);
            if ($model) {
                $out[sanitize_key($slug)] = sanitize_text_field($model);
            }
        }
        return $out;
    }

    /**
     * License key 保存后,同步到 License_Service (加密存储 + 触发验证)。
     */
    public static function on_license_saved(mixed $old, mixed $new, string $option)
    : void {
        if (class_exists('Linked3\\Classes\\License\\LicenseService')) {
            \Linked3\Classes\License\LicenseService::instance()->store_license_key((string) $new);
        }
    }

    public static function register_admin_menu()
    : void {
        // 顶级菜单已在 linked3.php 早期注册 (slug=linked3-dashboard, 回调=空)。
        // 这里注册一个同 slug 子菜单,WP 会自动让点击顶级时执行此回调。
        // 同时用 remove_submenu_page 移除 WP 自动生成的第一个子菜单项(避免重复)。
        add_submenu_page(
            'linked3-dashboard',
            '概览',
            '概览',
            'manage_options',
            'linked3-dashboard',
            [__CLASS__, 'render_dashboard']
        );
    }

    public static function render_dashboard()
    : void {
        if (!current_user_can('manage_options')) {
            wp_die(__('无权限', 'linked3'));
        }

        // 默认 overview 数据
        $overview = [
            'plan' => 'free',
            'tokens_today' => 0,
            'tokens_quota' => 50000,
            'tokens_remaining' => 50000,
            'ai_calls_30d' => 0,
            'tasks_active' => 0,
            'providers_configured' => 0,
        ];
        $chart = [];

        try {
            $dash = new Dashboard();
            $overview = $dash->overview();
            $chart = $dash->usage_chart(30);
        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p><strong>警告:</strong> '
                . esc_html($e->getMessage())
                . '</p></div>';
        }

        // 渲染统一 Tab 面板
        include LINKED3_DIR . 'admin/views/dashboard/tabs.php';
    }

}
