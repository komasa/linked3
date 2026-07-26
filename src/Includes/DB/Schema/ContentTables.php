<?php

declare(strict_types=1);
/**
 * ContentTables — SQL definitions for content/chat/publish/v15 tables.
 *
 * Tables:
 *   linked3_chat_logs              Chat sessions (UNIQUE session_id)
 *   linked3_guest_token_usage      Anonymous quota (UNIQUE session_id+bot_id)
 *   linked3_sse_message_cache      SSE resume cache (KEY expires_at)
 *   linked3_content_templates      Writing templates (UNIQUE user+name+type)
 *   linked3_interlink_map          Internal link graph
 *   linked3_push_logs              SEO push-engine response log (v0.4.1)
 *   linked3_publish_targets        Multi-target publish destinations (v0.5.1)
 *   linked3_publish_logs           Per-publish attempt log (v0.5.1)
 *   linked3_collect_sources        RSS/URL scrape sources (v0.5.6)
 *   linked3_publish_remote_id_map  v3.0.0 重发=更新映射
 *   linked3_v15_brand_profiles      v5.2.0 V15 6要素品牌配置
 *   linked3_v15_seeds               v5.2.0 V15 4种Seed预设库
 *   linked3_v15_chart_dna          v5.2.0 V15 30种图示索引
 *
 * @package Linked3\DB
 * @subpackage Schema
 */

namespace Linked3\Includes\DB\Schema;

if (!defined('ABSPATH')) exit;

final class ContentTables
{
    /**
     * @param string $p Table prefix
     * @param string $charset Charset collate
     * @param string $on_update ON UPDATE clause (empty on SQLite)
     * @return string[] CREATE TABLE statements
     */
    public static function definitions(string $p, string $charset, string $on_update): array {
        return [
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

            // 9) SEO push-engine response log (v0.4.1).
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

            // 14) linked3_publish_remote_id_map — v3.0.0 重发=更新映射
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
        ];
    }
}
