<?php

declare(strict_types=1);
/**
 * BillingTables — SQL definitions for billing/quota tables.
 *
 * Tables:
 *   linked3_usage_logs        AI call ledger + token counts
 *   linked3_billing_events    Webhook audit log (v4.9.4)
 *   linked3_image_logs        Image generation log (v2.2.0)
 *
 * @package Linked3\DB
 * @subpackage Schema
 */

namespace Linked3\Includes\DB\Schema;

if (!defined('ABSPATH')) exit;

final class BillingTables
{
    /**
     * @param string $p Table prefix
     * @param string $charset Charset collate
     * @param string $on_update ON UPDATE clause (empty on SQLite)
     * @return string[] CREATE TABLE statements
     */
    public static function definitions(string $p, string $charset, string $on_update): array {
        return [
            // 1) AI usage ledger — every API call logs here for billing & quota.
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

            // 15) linked3_billing_events — v4.9.4 webhook audit log.
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
        ];
    }
}
