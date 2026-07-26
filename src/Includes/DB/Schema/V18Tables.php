<?php

declare(strict_types=1);
/**
 * V18Tables — SQL definitions for V18 subsystem tables.
 *
 * Tables:
 *   linked3_v18_reverse_results   V18 逆向拆解结果 (v16.0.5)
 *   linked3_v18_ruliu_progress    V18 入流追踪进度 (v16.0.5)
 *   linked3_v18_quality_reports   V18 质量报告 (v16.0.5)
 *
 * @package Linked3\DB
 * @subpackage Schema
 */

namespace Linked3\Includes\DB\Schema;

if (!defined('ABSPATH')) exit;

final class V18Tables
{
    /**
     * @param string $p Table prefix
     * @param string $charset Charset collate
     * @param string $on_update ON UPDATE clause (empty on SQLite)
     * @return string[] CREATE TABLE statements
     */
    public static function definitions(string $p, string $charset, string $on_update): array {
        return [
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
}
