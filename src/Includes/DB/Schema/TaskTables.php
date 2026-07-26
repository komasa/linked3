<?php

declare(strict_types=1);
/**
 * TaskTables — SQL definitions for AutoGPT task tables.
 *
 * Tables:
 *   linked3_tasks         Task definitions (AutoGPT)
 *   linked3_task_queue    Queue items + attempts + error_message
 *
 * @package Linked3\DB
 * @subpackage Schema
 */

namespace Linked3\Includes\DB\Schema;

if (!defined('ABSPATH')) exit;

final class TaskTables
{
    /**
     * @param string $p Table prefix
     * @param string $charset Charset collate
     * @param string $on_update ON UPDATE clause (empty on SQLite)
     * @return string[] CREATE TABLE statements
     */
    public static function definitions(string $p, string $charset, string $on_update): array {
        return [
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
        ];
    }
}
