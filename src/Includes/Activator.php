<?php

declare(strict_types=1);
/**
 * Activator. Builds tables, sets DB version, schedules crons.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class Activator
{
    /**
     * Runs on register_activation_hook.
     *
     * @return void
     */
    public static function activate()
    : void {
        // ── FIX v16.0.1: Wrap entire activation in try/catch ──────────────
        // On WordPress Playground (SQLite + PHP 8.x + restricted env), any
        // uncaught Throwable during activation produces the generic
        // "There has been a critical error on this website" screen.
        // We catch everything, log it, and let activation succeed so the
        // admin can at least access the plugin settings page.
        try {
            static::do_activate();
        } catch (\Throwable $e) {
            // Log to WP error log so the admin can diagnose.
            if (function_exists('error_log')) {
                error_log('[Linked3] Activation warning: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
            // Store in option for the dashboard health card.
            if (function_exists('update_option')) {
                update_option('linked3_activation_warning', [
                    'message' => $e->getMessage(),
                    'time'    => time(),
                ]);
            }
            // Stamp the DB version so check_for_updates() doesn't re-trigger.
            if (function_exists('update_option') && defined('LINKED3_DB_VERSION_OPTION') && defined('LINKED3_DB_VERSION')) {
                update_option(LINKED3_DB_VERSION_OPTION, LINKED3_DB_VERSION);
            }
        }
    }

    /**
     * Actual activation logic — separated so activate() can wrap it in try/catch.
     *
     * @return void
     */
    public static function do_activate()
    : void {
        $version = defined('LINKED3_VERSION') ? LINKED3_VERSION : '0.0.0';
        self::create_tables($version);
        self::set_default_options($version);
        self::register_post_types();
        self::flush_rewrites();
        self::schedule_cron_jobs();
        update_option('linked3_activated_version', $version);
    }

    private static function create_tables(string $version): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_table_schemas($wpdb->prefix, $charset_collate);
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    private static function get_table_schemas(string $prefix, string $charset): array {
        return [
            "CREATE TABLE {$prefix}linked3_usage_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                module VARCHAR(50) NOT NULL DEFAULT 'general',
                provider VARCHAR(50) NOT NULL DEFAULT '',
                model VARCHAR(100) NOT NULL DEFAULT '',
                prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
                completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
                total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'ok',
                elapsed_ms INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_module (user_id, module),
                KEY provider_created (provider, created_at)
            ) {$charset};",
            "CREATE TABLE {$prefix}linked3_autogpt_tasks (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                config TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                next_run DATETIME NULL,
                PRIMARY KEY (id),
                KEY user_status (user_id, status)
            ) {$charset};",
            "CREATE TABLE {$prefix}linked3_book_projects (
                id VARCHAR(64) NOT NULL,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT '',
                state LONGTEXT,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_status (user_id, status)
            ) {$charset};",
        ];
    }

    private static function set_default_options(string $version): void {
        $defaults = [
            'linked3_default_provider' => 'siliconflow',
            'linked3_provider_models' => ['siliconflow' => 'Qwen/Qwen2.5-7B-Instruct'],
            'linked3_content_template' => 'blog_post',
            'linked3_seo_auto_optimize' => 1,
            'linked3_image_injection' => 0,
        ];
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    private static function register_post_types(): void {
        if (post_type_exists('linked3_content')) return;
        register_post_type('linked3_content', [
            'labels' => ['name' => 'Linked3 Content', 'singular_name' => 'Content'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
        ]);
    }

    private static function flush_rewrites(): void {
        flush_rewrite_rules();
    }

    private static function schedule_cron_jobs(): void {
        if (!wp_next_scheduled('linked3_autogpt_cron')) {
            wp_schedule_event(time(), '5min', 'linked3_autogpt_cron');
        }
        if (!wp_next_scheduled('linked3_distribute_retry')) {
            wp_schedule_event(time() + 300, '10min', 'linked3_distribute_retry');
        }
    }

    /**
     * v3.3.0: 预设默认 Provider 配置
     * 如果用户尚未配置任何 provider key,自动填入硅基流动测试 key
     * 用户可随时在 API 设置页修改或删除
     */
    public static function set_default_provider_config()
    : void {
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        if (!is_array($keys)) $keys = [];

        // 如果没有任何 key,填入硅基流动测试 key
        if (empty($keys) || empty($keys['siliconflow'])) {
            // v25.0: Secret_Vault handles demo key fallback
            // 用户可在后台 AI 设置中覆盖此默认值
            
            update_option(LINKED3_OPTION_PREFIX . 'provider_keys', $keys);
        }

        // 默认 provider 设为 siliconflow
        if (!get_option('linked3_default_provider')) {
            update_option('linked3_default_provider', 'siliconflow');
        }

        // 默认模型映射
        $models = get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        if (!is_array($models)) $models = [];
        if (empty($models['siliconflow'])) {
            $models['siliconflow'] = 'Qwen/Qwen2.5-7B-Instruct';
            update_option(LINKED3_OPTION_PREFIX . 'provider_models', $models);
        }

        // 默认 API base
        $bases = get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
        if (!is_array($bases)) $bases = [];
        if (empty($bases['siliconflow'])) {
            $bases['siliconflow'] = 'https://api.siliconflow.cn/v1';
            update_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', $bases);
        }
    }

    /**
     * Run on each blog for multi-site. Switches context, creates tables,
     * stamps DB version, then restores. Required because $wpdb->prefix is
     * blog-scoped — without switch_to_blog the secondary blogs would never
     * get their linked3_* tables created.
     *
     * @param int $blog_id
     * @return void
     */
    public static function setup_for_blog(int $blog_id)
    : void {
        if (!function_exists('switch_to_blog')) {
            return;
        }
        switch_to_blog($blog_id);
        if (class_exists('Linked3\\Includes\\DB\\MigrationRunner')) {
            \Linked3\Includes\DB\MigrationRunner::run_pending();
        } else {
            update_option(LINKED3_DB_VERSION_OPTION, LINKED3_DB_VERSION);
        }
        restore_current_blog();
    }

    /**
     * Self-heal: triggered on admin_init. If stored DB version is older
     * than the code's DB version, run migrations and stamp the new version.
     *
     * @return void
     */
    public static function check_for_updates()
    : void {
        // ── FIX v16.0.1: Wrap in try/catch — this runs on every admin_init ──
        // If the DB layer throws (e.g. INFORMATION_SCHEMA unsupported in
        // SQLite/Playground), we must NOT take down the entire admin.
        try {
            // Delegate to the migration runner (v0.1.2) which handles version
            // comparison + self-heal probe.
            if (class_exists('Linked3\\Includes\\DB\\MigrationRunner')) {
                \Linked3\Includes\DB\MigrationRunner::run_pending();
            }

            // v3.3.0: 升级时也填入默认 provider 配置 (无需重新激活)
            static::set_default_provider_config();
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[Linked3] check_for_updates warning: ' . $e->getMessage());
            }
        }

        // Cache clear (WP Rocket / object cache) — defensive.
        if (function_exists('rocket_clean_domain')) {
            @rocket_clean_domain(); // phpcs:ignore
        }
        if (function_exists('wp_cache_flush')) {
            @wp_cache_flush(); // phpcs:ignore
        }
    }

    /**
     * @return bool True if any expected table is missing.
     */
    public static function are_tables_missing(): bool
    {
        if (class_exists('Linked3\\Includes\\DB\\MigrationRunner')) {
            return \Linked3\Includes\DB\MigrationRunner::are_tables_missing();
        }
        return false;
    }

}
