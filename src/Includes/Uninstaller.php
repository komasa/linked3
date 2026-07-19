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
     * @return void
     */
    public static function uninstall()
    : void {
        global $wpdb;

        // 1) Drop tables (v5.0.0 schema — 16 tables, fetched dynamically
        //    from Schema::qualified_names() so new tables are auto-included).
        if (class_exists('Linked3\\Includes\\DB\\Schema')) {
            $tables = \Linked3\Includes\DB\Schema::qualified_names();
        } else {
            $tables = [];
        }
        if (!empty($tables)) {
            // phpcs:disable WordPress.DB.PreparedSQL -- table names are trusted constants.
            $sql = "DROP TABLE IF EXISTS " . implode(', ', array_map('esc_sql', $tables));
            $wpdb->query($sql);
            // phpcs:enable
        }

        // v5.0.0 (P2-3): clean up _linked3_indexed post_meta left by the
        // Content_Indexing_Processor. This meta marks posts as "already
        // vectorized" and must be purged on uninstall so a re-install
        // re-indexes from scratch.
        // v19.3.1: 使用 prepare 防注入（原为变量插值，虽用 WP 核心表但不符合防御深度原则）
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_linked3_indexed'));

        // v5.0.0 (P2-2): remove the uploads/linked3-logs/ directory.
        $uploads = wp_upload_dir();
        $log_dir = $uploads['basedir'] . '/linked3-logs';
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($log_dir);
        }

        // 2) Remove options. Allow modules to extend via filter.
        // v1.0.0 FINAL-AUDIT: expanded the hard-coded list to include all
        // sensitive options referenced across the codebase (provider keys,
        // license, push-engine credentials, form submissions, chat config).
        // Previously only `linked3_db_version` + `linked3_interlink_config`
        // were removed, leaving API keys + license key + push-engine tokens
        // in wp_options after uninstall — a data-leak / GDPR concern.
        $options = (array) apply_filters('linked3/uninstall_options', []);
        $options[] = LINKED3_DB_VERSION_OPTION;
        // Core options seeded by migrations / admin settings.
        $options[] = LINKED3_OPTION_PREFIX . 'interlink_config';
        // Sensitive credentials (v0.2.0/v0.5.0 hardening stored these encrypted,
        // but the ciphertext itself must be purged on uninstall).
        $options[] = LINKED3_OPTION_PREFIX . 'provider_keys';
        $options[] = LINKED3_OPTION_PREFIX . 'license_key';
        $options[] = LINKED3_OPTION_PREFIX . 'last_known_plan';
        $options[] = LINKED3_OPTION_PREFIX . 'last_seen_time';
        $options[] = LINKED3_OPTION_PREFIX . 'active_experiment';
        $options[] = LINKED3_OPTION_PREFIX . 'experiment_started_at';
        $options[] = LINKED3_OPTION_PREFIX . 'baseline_metrics';
        // SEO push engine credentials (Baidu / Toutiao / Google / Indexnow).
        $options[] = LINKED3_OPTION_PREFIX . 'push_baidu';
        $options[] = LINKED3_OPTION_PREFIX . 'push_toutiao';
        $options[] = LINKED3_OPTION_PREFIX . 'push_google';
        $options[] = LINKED3_OPTION_PREFIX . 'push_indexnow_key';
        $options[] = LINKED3_OPTION_PREFIX . 'google_jwt_token';
        // AI Forms + submissions.
        $options[] = LINKED3_OPTION_PREFIX . 'ai_forms';
        $options[] = LINKED3_OPTION_PREFIX . 'ai_form_submissions';
        // Chat config.
        $options[] = LINKED3_OPTION_PREFIX . 'chat_floating_enabled';
        $options[] = LINKED3_OPTION_PREFIX . 'chat_title';
        $options[] = LINKED3_OPTION_PREFIX . 'chat_greeting';
        $options[] = LINKED3_OPTION_PREFIX . 'chat_system_prompt';
        $options[] = LINKED3_OPTION_PREFIX . 'chat_use_rag';
        $options[] = LINKED3_OPTION_PREFIX . 'chat_bots';
        $options[] = LINKED3_OPTION_PREFIX . 'guest_chat_limit';
        // Moderation.
        $options[] = LINKED3_OPTION_PREFIX . 'moderation_banned_words';
        $options[] = LINKED3_OPTION_PREFIX . 'moderation_banned_ips';
        $options[] = LINKED3_OPTION_PREFIX . 'moderation_openai_enabled';
        // WC AI.
        $options[] = LINKED3_OPTION_PREFIX . 'wc_ai_reviews_enabled';
        $options[] = LINKED3_OPTION_PREFIX . 'wc_ai_review_disclaimer';
        // Speech / TTS / STT.
        $options[] = LINKED3_OPTION_PREFIX . 'tts_provider';
        $options[] = LINKED3_OPTION_PREFIX . 'tts_model';
        $options[] = LINKED3_OPTION_PREFIX . 'stt_provider';
        $options[] = LINKED3_OPTION_PREFIX . 'stt_model';
        // Addons.
        $options[] = LINKED3_OPTION_PREFIX . 'addon_ip_anon';
        $options[] = LINKED3_OPTION_PREFIX . 'addon_consent';
        foreach ($options as $key) {
            delete_option($key);
        }

        // 3) Clear crons (mirror Deactivator + any module-scheduled ones).
        // v1.0.0 FINAL-AUDIT: aligned with the corrected Deactivator list
        // (was missing 4 crons + had wrong hook name linked3_push_log_retention).
        $crons = (array) apply_filters('linked3/uninstall_crons', []);
        $crons[] = 'linked3_daily_health_check';
        $crons[] = 'linked3_token_reset';
        $crons[] = 'linked3_sse_cache_cleanup';
        $crons[] = 'linked3_log_prune';
        $crons[] = 'linked3_push_log_prune';
        $crons[] = 'linked3_billing_events_prune'; // v5.0.0
        $crons[] = 'linked3_license_heartbeat';
        $crons[] = 'linked3_subscription_check';
        $crons[] = 'linked3_business_optimize';
        $crons[] = 'linked3_autogpt_run';
        foreach ($crons as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        // 4) Multi-site: iterate blogs.
        if (is_multisite()) {
            $sites = get_sites(['fields' => 'ids', 'number' => 0]);
            foreach ($sites as $blog_id) {
                switch_to_blog($blog_id);
                // Drop tables on each blog.
                if (class_exists('Linked3\\Includes\\DB\\Schema')) {
                    $blog_tables = \Linked3\Includes\DB\Schema::qualified_names();
                    if (!empty($blog_tables)) {
                        $blog_sql = "DROP TABLE IF EXISTS " . implode(', ', array_map('esc_sql', $blog_tables));
                        $wpdb->query($blog_sql);
                    }
                }
                foreach ($options as $key) {
                    delete_option($key);
                }
                foreach ($crons as $hook) {
                    wp_clear_scheduled_hook($hook);
                }
                restore_current_blog();
            }
        }
    }
}

// WordPress calls uninstall.php or register_uninstall_hook. We expose both
// via the static method, and let the main plugin file register the hook.
