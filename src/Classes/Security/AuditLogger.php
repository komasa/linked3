<?php

declare(strict_types=1);
/**
 * AuditLogger — extracted from RateLimiterV2.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Security
 */

namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) exit;

class AuditLogger {
    private static ?AuditLogger $instance = null;

    public static function instance(): AuditLogger {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->ensureTable();
    }

    /**
     * 记录审计日志。
     */
    public function log(string $action, array $details = []): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_audit_logs';

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (user_id, action, details, ip, user_agent, time) VALUES (%s, %s, %s, %s, %s, %s)",
            get_current_user_id(), $action, wp_json_encode($details, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), current_time('mysql')
        ));
    }

    /**
     * 查询审计日志。
     */
    public function getLogs(int $limit = 50, string $action = '', int $userId = 0): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_audit_logs';
        $where = '1=1';
        $params = [];
        if ($action) { $where .= ' AND action = %s'; $params[] = $action; }
        if ($userId) { $where .= ' AND user_id = %d'; $params[] = $userId; }
        $where .= ' ORDER BY time DESC LIMIT %d';
        $params[] = $limit;
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE {$where}", ...$params);
        return $wpdb->get_results($query, ARRAY_A); // $wpdb->prepare applied above
    }

    /**
     * 清理旧日志 (保留90天)。
     */
    public function cleanup(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_audit_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE time < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
    }

    private function ensureTable(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_audit_logs';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED DEFAULT 0,
                action VARCHAR(100) NOT NULL,
                details LONGTEXT,
                ip VARCHAR(45),
                user_agent VARCHAR(255),
                time DATETIME NOT NULL,
                INDEX idx_action (action),
                INDEX idx_user (user_id),
                INDEX idx_time (time)
            ) {$charset};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }
}

/**
 * Linked3 Security Bootstrap — v5.7.0
 */
