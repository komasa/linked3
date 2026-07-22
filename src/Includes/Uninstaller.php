<?php

declare(strict_types=1);
/**
 * Uninstaller. Called when user clicks "Delete" in Plugins screen.
 * Removes all tables + options + crons. No soft-delete.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

// This file is loaded directly by WordPress on uninstall — constants
// we expect may not exist, so re-declare defensively.
if (!defined('LINKED3_OPTION_PREFIX')) {
    define('LINKED3_OPTION_PREFIX', 'linked3_');
}
if (!defined('LINKED3_DB_VERSION_OPTION')) {
    define('LINKED3_DB_VERSION_OPTION', 'linked3_db_version');
}

final class Uninstaller
{
    /**
     * Runs on uninstall (user clicks "Delete" in Plugins screen).
     * Removes all tables, options, and cron events. No soft-delete.
     *
     * @return void
     */
    public static function uninstall(): void
    {
        global $wpdb;

        // Clear all scheduled cron events.
        $cron_hooks = [
            'linked3_daily_health_check',
            'linked3_token_reset',
            'linked3_sse_cache_cleanup',
            'linked3_log_prune',
            'linked3_license_heartbeat',
            'linked3_subscription_check',
            'linked3_business_optimize',
            'linked3_autogpt_run',
        ];
        foreach ($cron_hooks as $hook) {
            $timestamp = function_exists('wp_next_scheduled') ? wp_next_scheduled($hook) : false;
            while ($timestamp !== false) {
                wp_unschedule_event($timestamp, $hook);
                $timestamp = wp_next_scheduled($hook);
            }
        }

        // Drop all linked3_* tables (use Schema::tables() if available).
        $tables = [];
        if (class_exists('Linked3\\Includes\\DB\\Schema')) {
            $tables = \Linked3\Includes\DB\Schema::tables();
        } else {
            // Fallback hardcoded list — must match Schema::tables().
            $tables = [
                'linked3_usage_logs',
                'linked3_tasks',
                'linked3_task_queue',
                'linked3_chat_logs',
                'linked3_guest_token_usage',
                'linked3_sse_message_cache',
                'linked3_content_templates',
                'linked3_interlink_map',
                'linked3_push_logs',
                'linked3_publish_targets',
                'linked3_publish_logs',
                'linked3_collect_sources',
                'linked3_image_logs',
                'linked3_publish_remote_id_map',
                'linked3_billing_events',
                'linked3_v15_brand_profiles',
                'linked3_v15_seeds',
                'linked3_v15_chart_dna',
                'linked3_v18_reverse_results',
                'linked3_v18_ruliu_progress',
                'linked3_v18_quality_reports',
            ];
        }

        foreach ($tables as $table) {
            $full_name = $wpdb->prefix . $table;
            // phpcs:ignore WordPress.DB.PreparedSQL
            $wpdb->query("DROP TABLE IF EXISTS `{$full_name}`");
        }

        // Delete all linked3_% options.
        // phpcs:ignore WordPress.DB.PreparedSQL
        $option_names = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'linked3\_%'"
        );
        foreach ($option_names as $option_name) {
            delete_option($option_name);
        }

        // Delete transients.
        // phpcs:ignore WordPress.DB.PreparedSQL
        $transient_names = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '\_transient\_linked3\_%'
                OR option_name LIKE '\_site\_transient\_linked3\_%'"
        );
        foreach ($transient_names as $transient_name) {
            delete_option($transient_name);
        }

        // Flush rewrite rules.
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }
    }
}

// WordPress calls uninstall.php or register_uninstall_hook. We expose both
// via the static method, and let the main plugin file register the hook.
