<?php

declare(strict_types=1);
/**
 * Database schema — 12 tables powering linked3.0.
 *
 * Mirrors aipower's `database-schema.php` philosophy:
 *   - dbDelta-safe (no DROP, no ALTER outside dbDelta)
 *   - UNIQUE constraints for natural keys (dedup)
 *   - composite indexes for hot query paths
 *   - `updated_at ON UPDATE CURRENT_TIMESTAMP` auto-maintained
 *
 * Tables:
 *   linked3_usage_logs          AI call ledger + token counts
 *   linked3_tasks               Task definitions (AutoGPT)
 *   linked3_task_queue          Queue items + attempts + error_message
 *   linked3_chat_logs           Chat sessions (UNIQUE session_id)
 *   linked3_guest_token_usage   Anonymous quota (UNIQUE session_id+bot_id)
 *   linked3_sse_message_cache   SSE resume cache (KEY expires_at)
 *   linked3_content_templates   Writing templates (UNIQUE user+name+type)
 *   linked3_interlink_map       Internal link graph
 *   linked3_push_logs           SEO push-engine response log (v0.4.1)
 *   linked3_publish_targets     Multi-target publish destinations (v0.5.1)
 *   linked3_publish_logs        Per-publish attempt log (v0.5.1)
 *   linked3_collect_sources     RSS/URL scrape sources (v0.5.6)
 *
 * @package Linked3
 * @subpackage DB
 */

namespace Linked3\Includes\DB;

if (!defined('ABSPATH')) {
    exit;
}

final class Schema
{
    /** @return string[] Table names (unqualified; caller adds $wpdb->prefix). */
    public static function tables()
    : array {
        return [
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
            'linked3_publish_remote_id_map', // v3.0.0: 重发=更新映射
            'linked3_billing_events', // v4.9.4: webhook audit log
            'linked3_v15_brand_profiles', // v5.2.0: V15 6要素品牌配置
            'linked3_v15_seeds', // v5.2.0: V15 4种Seed预设库
            'linked3_v15_chart_dna', // v5.2.0: V15 30种图示索引
            // v16.0.5: V18 subsystem tables — consolidated into main Schema
            'linked3_v18_reverse_results', // V18 逆向拆解结果
            'linked3_v18_ruliu_progress',  // V18 入流追踪进度
            'linked3_v18_quality_reports', // V18 质量报告
        ];
    }

    /**
     * @return string[] Fully-qualified CREATE TABLE statements.
     */
    public static function definitions()
    : array {
        global $wpdb;
        $p = $wpdb->prefix;
        $charset = $wpdb->get_charset_collate();

        // ── FIX v16.0.1: SQLite/Playground compatibility ──────────────────
        // WordPress Playground uses SQLite via wp-sqlite-db. SQLite does NOT
        // support `ON UPDATE CURRENT_TIMESTAMP` natively (it requires a
        // trigger). While wp-sqlite-db 2.x creates triggers for this, older
        // versions and some forks do not. We omit the clause entirely on
        // non-MySQL databases — the plugin's PHP code already sets
        // `updated_at` explicitly on every UPDATE, so the auto-update
        // behavior is not required for correctness.
        $on_update = (isset($wpdb->is_mysql) && $wpdb->is_mysql)
            ? 'ON UPDATE CURRENT_TIMESTAMP'
            : '';

        return [
            // 1) AI usage ledger — every API call logs here for billing & quota.
            //    v4.5.8: added idx_user_created for dashboard "30-day usage"
            //    query (was idx_user_module_time alone, forcing a scan when
            //    module filter is absent).
            "CREATE TABLE {$p}linked3_usage_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                session_id VARCHAR(64) NOT NULL DEFAULT '',
                module VARCHAR(32) NOT NULL DEFAULT 'general',
                provider VARCHAR(32) NOT NULL DEFAULT '',
                model VARCHAR(64) NOT NULL DEFAULT '',
                prompt_tokens INT(11) UNSIGNED NOT NULL DEFAULT 0,
                completion_tokens INT(11) UNSIGNED NOT NULL DEFAULT 0,
                total_tokens INT(11) UNSIGNED NOT NULL DEFAULT 0,
                cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
                status VARCHAR(16) NOT NULL DEFAULT 'ok',
                error_code VARCHAR(64) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_module_time (user_id, module, created_at),
                KEY idx_session (session_id),
                KEY idx_created (created_at),
                KEY idx_user_created (user_id, created_at)
            ) {$charset};",

            // 2) AutoGPT task definitions.
            "CREATE TABLE {$p}linked3_tasks (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                task_type VARCHAR(32) NOT NULL DEFAULT 'content-writing',
                name VARCHAR(191) NOT NULL DEFAULT '',
                config LONGTEXT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                next_run_time DATETIME NULL DEFAULT NULL,
                last_run_time DATETIME NULL DEFAULT NULL,
                last_run_status VARCHAR(16) NOT NULL DEFAULT '',
                run_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                KEY idx_status_next (status, next_run_time),
                KEY idx_user_type (user_id, task_type)
            ) {$charset};",

            // 3) Task queue — attempts + error_message for retry.
            "CREATE TABLE {$p}linked3_task_queue (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                task_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                payload LONGTEXT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                attempts INT(11) UNSIGNED NOT NULL DEFAULT 0,
                max_attempts INT(11) UNSIGNED NOT NULL DEFAULT 3,
                error_message TEXT NULL,
                last_attempt_time DATETIME NULL DEFAULT NULL,
                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                scheduled_for DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_status_added (status, added_at),
                KEY idx_task (task_id),
                KEY idx_scheduled (scheduled_for)
            ) {$charset};",

            // 4) Chat sessions — one row per conversation.
            "CREATE TABLE {$p}linked3_chat_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                bot_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                session_id VARCHAR(64) NOT NULL DEFAULT '',
                conversation_uuid VARCHAR(64) NOT NULL DEFAULT '',
                module VARCHAR(32) NOT NULL DEFAULT 'chat',
                messages LONGTEXT NOT NULL,
                message_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
                tokens_used INT(11) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                UNIQUE KEY uniq_session (bot_id, user_id, session_id, module),
                KEY idx_user_time (user_id, created_at),
                KEY idx_bot (bot_id)
            ) {$charset};",

            // 5) Anonymous guest quota — per session per bot.
            "CREATE TABLE {$p}linked3_guest_token_usage (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                session_id VARCHAR(64) NOT NULL DEFAULT '',
                bot_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                tokens_used INT(11) UNSIGNED NOT NULL DEFAULT 0,
                requests INT(11) UNSIGNED NOT NULL DEFAULT 0,
                reset_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                UNIQUE KEY uniq_guest (session_id, bot_id),
                KEY idx_reset (reset_at)
            ) {$charset};",

            // 6) SSE resume cache — short-lived message buffer for reconnect.
            "CREATE TABLE {$p}linked3_sse_message_cache (
                cache_key VARCHAR(128) NOT NULL DEFAULT '',
                payload LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                PRIMARY KEY (cache_key),
                KEY idx_expires (expires_at)
            ) {$charset};",

            // 7) Content writer templates — per-user, deduped by name+type.
            //    v5.1.1: added template_category + pipeline_stage columns for
            //    the dual-class template system (content + pipeline).
            "CREATE TABLE {$p}linked3_content_templates (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                template_name VARCHAR(191) NOT NULL DEFAULT '',
                template_type VARCHAR(32) NOT NULL DEFAULT 'article',
                template_category VARCHAR(16) NOT NULL DEFAULT 'content',
                pipeline_stage VARCHAR(32) NOT NULL DEFAULT '',
                config LONGTEXT NOT NULL,
                post_type VARCHAR(32) NOT NULL DEFAULT 'post',
                post_status VARCHAR(32) NOT NULL DEFAULT 'publish',
                post_author BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                schedule_datetime DATETIME NULL DEFAULT NULL,
                categories TEXT NULL,
                is_starter TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                UNIQUE KEY uniq_template (user_id, template_category, pipeline_stage, template_name),
                KEY idx_type (template_type),
                KEY idx_category (template_category),
                KEY idx_pipeline_stage (pipeline_stage)
            ) {$charset};",

            // 8) Internal link graph — source→target anchor map.
            "CREATE TABLE {$p}linked3_interlink_map (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                source_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                target_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                anchor VARCHAR(191) NOT NULL DEFAULT '',
                count INT(11) UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_link (source_post_id, target_post_id, anchor),
                KEY idx_source (source_post_id),
                KEY idx_target (target_post_id)
            ) {$charset};",

            // 9) SEO push-engine response log (v0.4.1). One row per
            //    (engine, URL) push attempt. Status: pending|success|fail.
            //    Retention: prune_older_than(30 days) via cron (added in
            //    Hook_Manager::prune_push_logs).
            //    v4.5.8: added idx_url_engine_status for count_recent_success()
            //    hot path (was idx_url alone, forcing a filesort on engine+status).
            "CREATE TABLE {$p}linked3_push_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                engine VARCHAR(32) NOT NULL DEFAULT '',
                url VARCHAR(2048) NOT NULL DEFAULT '',
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                response_code INT(11) NOT NULL DEFAULT 0,
                response_body TEXT NULL,
                message VARCHAR(255) NOT NULL DEFAULT '',
                retries INT(11) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_engine_status (engine, status, created_at),
                KEY idx_url (url),
                KEY idx_url_engine_status (url, engine, status, created_at),
                KEY idx_created (created_at)
            ) {$charset};",

            // 10) Publish targets — multi-destination publish config (v0.5.1).
            //     config encrypted at rest via Crypto (holds remote creds).
            "CREATE TABLE {$p}linked3_publish_targets (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                name VARCHAR(191) NOT NULL DEFAULT '',
                type VARCHAR(32) NOT NULL DEFAULT 'local',
                config LONGTEXT NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                KEY idx_user_type (user_id, type),
                KEY idx_default (user_id, is_default)
            ) {$charset};",

            // 11) Publish attempt log — one row per (target, post).
            "CREATE TABLE {$p}linked3_publish_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                target_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                response_code INT(11) NOT NULL DEFAULT 0,
                remote_id VARCHAR(64) NOT NULL DEFAULT '',
                message TEXT NULL,
                attempts INT(11) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_target (target_id, created_at),
                KEY idx_post (post_id),
                KEY idx_status (status)
            ) {$charset};",

            // 12) Collect sources — RSS / URL scrape registry (v0.5.6).
            "CREATE TABLE {$p}linked3_collect_sources (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                type VARCHAR(16) NOT NULL DEFAULT 'rss',
                name VARCHAR(191) NOT NULL DEFAULT '',
                config LONGTEXT NOT NULL,
                schedule VARCHAR(32) NOT NULL DEFAULT 'daily',
                keywords_include TEXT NULL,
                keywords_exclude TEXT NULL,
                last_fetched DATETIME NULL DEFAULT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                KEY idx_user_type (user_id, type),
                KEY idx_schedule (schedule, status)
            ) {$charset};",

            // 13) 图片生成日志 (v2.2.0)
            "CREATE TABLE {$p}linked3_image_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                provider VARCHAR(32) NOT NULL DEFAULT '',
                model VARCHAR(64) NOT NULL DEFAULT '',
                prompt VARCHAR(500) NOT NULL DEFAULT '',
                url VARCHAR(2048) NOT NULL DEFAULT '',
                status VARCHAR(16) NOT NULL DEFAULT 'ok',
                cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_provider_time (provider, created_at),
                KEY idx_created (created_at)
            ) {$charset};",

            // 14) linked3_publish_remote_id_map — v3.0.0 重发=更新映射
            //   (local_post_id, target_id) → remote_id 唯一索引
            "CREATE TABLE {$p}linked3_publish_remote_id_map (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                local_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                target_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                remote_id VARCHAR(64) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                UNIQUE KEY uq_post_target (local_post_id, target_id),
                KEY idx_target (target_id)
            ) {$charset};",

            // 15) linked3_billing_events — v4.9.4 webhook audit log.
            //   Stores raw webhook payloads + parsed results for billing
            //   reconciliation. Retention: prune_older_than(90 days) via cron.
            "CREATE TABLE {$p}linked3_billing_events (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type VARCHAR(64) NOT NULL DEFAULT '',
                provider VARCHAR(32) NOT NULL DEFAULT '',
                license_key VARCHAR(191) NOT NULL DEFAULT '',
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                plan VARCHAR(32) NOT NULL DEFAULT '',
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(8) NOT NULL DEFAULT 'USD',
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                raw_payload LONGTEXT NOT NULL,
                signature VARCHAR(128) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_event_type (event_type, created_at),
                KEY idx_license (license_key),
                KEY idx_user (user_id, created_at),
                KEY idx_status (status)
            ) {$charset};",

            // 16) linked3_v15_brand_profiles — v5.2.0 V15 6要素品牌配置
            "CREATE TABLE {$p}linked3_v15_brand_profiles (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                profile_name VARCHAR(191) NOT NULL DEFAULT '',
                brand_name VARCHAR(191) NOT NULL DEFAULT '',
                brand_logo VARCHAR(191) NOT NULL DEFAULT '',
                brand_font VARCHAR(191) NOT NULL DEFAULT '',
                signature_name VARCHAR(191) NOT NULL DEFAULT '',
                signature_title VARCHAR(191) NOT NULL DEFAULT '',
                signature_slogan TEXT NULL,
                color_primary VARCHAR(16) NOT NULL DEFAULT '#1B3A5C',
                color_secondary VARCHAR(16) NOT NULL DEFAULT '#C8403C',
                color_neutral VARCHAR(16) NOT NULL DEFAULT '#E8E4DD',
                color_accent VARCHAR(16) NOT NULL DEFAULT '#C9A961',
                mood_primary VARCHAR(64) NOT NULL DEFAULT '冷静理性',
                mood_secondary VARCHAR(64) NOT NULL DEFAULT '严肃紧迫',
                culture_region VARCHAR(191) NOT NULL DEFAULT '',
                culture_age VARCHAR(64) NOT NULL DEFAULT '',
                culture_occupation VARCHAR(191) NOT NULL DEFAULT '',
                culture_subculture VARCHAR(191) NOT NULL DEFAULT '',
                platform_name VARCHAR(64) NOT NULL DEFAULT '小红书',
                platform_size VARCHAR(32) NOT NULL DEFAULT '1080x1440',
                platform_ratio VARCHAR(16) NOT NULL DEFAULT '3:4',
                density VARCHAR(32) NOT NULL DEFAULT '标准16节点',
                product_type VARCHAR(64) NOT NULL DEFAULT '单图Card',
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP {$on_update},
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_default (user_id, is_default)
            ) {$charset};",

            // 17) linked3_v15_seeds — v5.2.0 V15 4种Seed预设库
            "CREATE TABLE {$p}linked3_v15_seeds (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                seed_id VARCHAR(64) NOT NULL DEFAULT '',
                seed_type VARCHAR(32) NOT NULL DEFAULT 'InfoSeed',
                seed_name VARCHAR(191) NOT NULL DEFAULT '',
                seed_config LONGTEXT NOT NULL,
                is_starter TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_seed (user_id, seed_id),
                KEY idx_type (seed_type)
            ) {$charset};",

            // 18) linked3_v15_chart_dna — v5.2.0 V15 30种图示索引
            "CREATE TABLE {$p}linked3_v15_chart_dna (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                dna_code VARCHAR(8) NOT NULL DEFAULT '',
                chart_name_zh VARCHAR(64) NOT NULL DEFAULT '',
                chart_name_en VARCHAR(64) NOT NULL DEFAULT '',
                category VARCHAR(32) NOT NULL DEFAULT '',
                use_case TEXT NULL,
                prompt_template TEXT NULL,
                is_starter TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_dna (dna_code),
                KEY idx_category (category)
            ) {$charset};",

            // 19) linked3_v18_reverse_results — V18 逆向拆解结果 (v16.0.5)
            "CREATE TABLE {$p}linked3_v18_reverse_results (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                engineer_type VARCHAR(100) NOT NULL DEFAULT '',
                target_hash VARCHAR(64) NOT NULL DEFAULT '',
                target_description TEXT NULL,
                result_json LONGTEXT NULL,
                validation_score INT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_engineer_type (engineer_type),
                KEY idx_target_hash (target_hash),
                KEY idx_created_at (created_at)
            ) {$charset};",

            // 20) linked3_v18_ruliu_progress — V18 入流追踪进度 (v16.0.5)
            "CREATE TABLE {$p}linked3_v18_ruliu_progress (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                day_number INT NOT NULL DEFAULT 0,
                state VARCHAR(50) NOT NULL DEFAULT '',
                progress_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
                metrics_json TEXT NULL,
                notes TEXT NULL,
                PRIMARY KEY (id),
                KEY idx_day_number (day_number),
                KEY idx_state (state)
            ) {$charset};",

            // 21) linked3_v18_quality_reports — V18 质量报告 (v16.0.5)
            "CREATE TABLE {$p}linked3_v18_quality_reports (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                target_type VARCHAR(100) NOT NULL DEFAULT '',
                target_hash VARCHAR(64) NOT NULL DEFAULT '',
                overall_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                gate_results_json LONGTEXT NULL,
                suggestions_json TEXT NULL,
                PRIMARY KEY (id),
                KEY idx_target_type (target_type),
                KEY idx_overall_score (overall_score),
                KEY idx_created_at (created_at)
            ) {$charset};",
        ];
    }

    /**
     * Create all tables (idempotent via dbDelta).
     *
     * @return void
     */
    public static function create_all()
    : void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $errors = [];

        foreach (self::definitions() as $sql) {
            // dbDelta returns an array of messages; errors are included.
            $result = dbDelta($sql);

            // ── FIX v16.0.1: Verify each table was actually created ──────
            // On SQLite (WordPress Playground), dbDelta may silently fail
            // if the SQL contains syntax the wp-sqlite-db shim can't
            // translate. We verify the table exists and, if not, try a
            // direct $wpdb->query() as a fallback.
            $table_name = self::extract_table_name($sql);
            if ($table_name) {
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                if ($exists !== $table_name) {
                    // Fallback: try direct query (bypasses dbDelta's parser).
                    $fallback_result = $wpdb->query($sql); // $wpdb->prepare not needed — $sql is DDL from definitions()
                    if ($fallback_result === false) {
                        $errors[] = sprintf(
                            'Failed to create table %s: dbDelta=%s, direct=%s',
                            $table_name,
                            wp_json_encode($result),
                            $wpdb->last_error
                        );
                    }
                }
            }
        }

        // Log any errors for debugging.
        if (!empty($errors) && function_exists('error_log')) {
            foreach ($errors as $err) {
                error_log('[Linked3] Schema: ' . $err);
            }
            // Store in option for the dashboard health card.
            if (function_exists('update_option')) {
                update_option('linked3_schema_errors', $errors);
            }
        }

        // Stamp the DB version so check_for_updates() knows we're current.
        update_option(LINKED3_DB_VERSION_OPTION, LINKED3_DB_VERSION);
    }

    /**
     * Extract the table name from a CREATE TABLE statement.
     *
     * @param string $sql
     * @return string|null
     */
    private static function extract_table_name(string $sql): ?string
    {
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([^\s`"(]+)/i', $sql, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @return string[] Fully-qualified table names (with prefix).
     */
    public static function qualified_names(): array
    {
        global $wpdb;
        $out = [];
        foreach (self::tables() as $t) {
            $out[] = $wpdb->prefix . $t;
        }
        return $out;
    }
}
