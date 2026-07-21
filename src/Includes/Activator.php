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
    private static function do_activate()
    : void {
        // Activation runs BEFORE plugins_loaded, so the Dependency_Loader has
        // not run yet. We must manually require the DB classes we need.
        if (!class_exists('Linked3\\Includes\\DB\\Schema')) {
            $schema_file = LINKED3_DIR . 'src/Includes/DB/Schema.php';
            if (file_exists($schema_file)) require_once $schema_file;
        }
        if (!class_exists('Linked3\\Includes\\DB\\MigrationRunner')) {
            $runner_file = LINKED3_DIR . 'src/Includes/DB/MigrationRunner.php';
            if (file_exists($runner_file)) require_once $runner_file;
        }

        // Create all tables + run pending migrations.
        if (class_exists('Linked3\\Includes\\DB\\MigrationRunner')) {
            \Linked3\Includes\DB\MigrationRunner::run_pending();
        } else {
            update_option(LINKED3_DB_VERSION_OPTION, LINKED3_DB_VERSION);
        }

        self::schedule_cron_events();

        // Multi-site: tables per blog.
        if (is_multisite()) {
            $sites = get_sites(['fields' => 'ids', 'number' => 0]);
            foreach ($sites as $blog_id) {
                static::setup_for_blog($blog_id);
            }
        }

        // v3.3.0: 预设默认 Provider (硅基流动 + 测试 API key),方便用户开箱即用
        static::set_default_provider_config();
        self::set_default_siliconflow_config();

        flush_rewrite_rules();
    }

    /**
     * Schedule all cron events needed by the plugin.
     *
     * @return void
     */
    private static function schedule_cron_events(): void
    {
        $schedules = [
            ['linked3_daily_health_check',     time() + HOUR_IN_SECONDS,                 'daily'],
            ['linked3_token_reset',            strtotime('tomorrow 00:05'),              'daily'],
            ['linked3_sse_cache_cleanup',      time() + 10 * MINUTE_IN_SECONDS,          'linked3_every_10min'],
            ['linked3_log_prune',              time() + HOUR_IN_SECONDS,                 'daily'],
            ['linked3_license_heartbeat',      time() + HOUR_IN_SECONDS,                 'daily'],
            ['linked3_subscription_check',     time() + 2 * HOUR_IN_SECONDS,             'daily'],
            ['linked3_business_optimize',      time() + 3 * HOUR_IN_SECONDS,             'daily'],
            ['linked3_autogpt_run',            time() + 5 * MINUTE_IN_SECONDS,           'linked3_every_10min'],
        ];
        foreach ($schedules as [$hook, $ts, $recurrence]) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event($ts, $recurrence, $hook);
            }
        }
    }

    /**
     * Set default SiliconFlow provider config (v3.8.0).
     *
     * @return void
     */
    private static function set_default_siliconflow_config(): void
    {
        try {
            $keys = get_option('linked3_provider_keys', []);
            if (!is_array($keys)) $keys = [];
            if (empty($keys) || empty($keys['siliconflow'])) {
                update_option('linked3_provider_keys', $keys);
            }
            if (!get_option('linked3_default_provider')) {
                update_option('linked3_default_provider', 'siliconflow');
            }
            $models = get_option('linked3_provider_models', []);
            if (!is_array($models)) $models = [];
            if (empty($models['siliconflow'])) {
                $models['siliconflow'] = 'Qwen/Qwen2.5-7B-Instruct';
                update_option('linked3_provider_models', $models);
            }
        } catch (\Throwable $e) {
            // 静默
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
