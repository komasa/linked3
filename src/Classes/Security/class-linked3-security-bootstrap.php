<?php
/**
 * Linked3 Rate Limiter V2 — v5.7.0.4
 *
 * 功能:
 *   - 多维度速率限制 (IP/User/API/AJAX)
 *   - 滑动窗口算法
 *   - 429 响应 + Retry-After 头
 *   - 事件驱动: rate_limited.exceeded
 *
 * @package Linked3\Security
 * @since 5.7.0.4
 */
namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) exit;

class Linked3_Rate_Limiter_V2 {
    private static ?Linked3_Rate_Limiter_V2 $instance = null;
    private array $buckets = [];
    private array $limits = [
        'ip_minute' => ['max' => 60, 'window' => 60],       // IP 每分钟60次
        'user_hour' => ['max' => 100, 'window' => 3600],    // 用户每小时100次
        'ai_minute' => ['max' => 20, 'window' => 60],       // AI 每分钟20次
        'ajax_minute' => ['max' => 30, 'window' => 60],     // AJAX 每分钟30次
    ];

    public static function instance(): Linked3_Rate_Limiter_V2 {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 检查是否允许 (滑动窗口)。
     */
    public function check(string $key, string $limitType = 'ip_minute'): bool {
        $limit = $this->limits[$limitType] ?? ['max' => 60, 'window' => 60];
        $now = time();

        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [];
        }
        // 清理过期
        $this->buckets[$key] = array_filter(
            $this->buckets[$key],
            fn($t) => ($now - $t) < $limit['window']
        );

        if (count($this->buckets[$key]) >= $limit['max']) {
            linked3_dispatch('linked3.rate_limited.exceeded', [
                'key' => $key,
                'type' => $limitType,
                'max' => $limit['max'],
                'window' => $limit['window'],
            ]);
            return false;
        }
        $this->buckets[$key][] = $now;
        return true;
    }

    /**
     * 获取剩余配额。
     */
    public function getRemaining(string $key, string $limitType = 'ip_minute'): int {
        $limit = $this->limits[$limitType] ?? ['max' => 60, 'window' => 60];
        $now = time();
        $count = 0;
        if (isset($this->buckets[$key])) {
            foreach ($this->buckets[$key] as $t) {
                if (($now - $t) < $limit['window']) $count++;
            }
        }
        return max(0, $limit['max'] - $count);
    }

    /**
     * 获取 Retry-After 秒数。
     */
    public function getRetryAfter(string $key, string $limitType = 'ip_minute'): int {
        $limit = $this->limits[$limitType] ?? ['max' => 60, 'window' => 60];
        if (empty($this->buckets[$key])) return 0;
        $oldest = min($this->buckets[$key]);
        return max(0, $limit['window'] - (time() - $oldest));
    }

    /**
     * IP 限流门 (AJAX 入口调用)。
     */
    public function gateIP(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return $this->check('ip:' . $ip, 'ip_minute');
    }

    /**
     * 用户限流门。
     */
    public function gateUser(int $userId): bool {
        return $this->check('user:' . $userId, 'user_hour');
    }

    /**
     * AI 调用限流门。
     */
    public function gateAI(int $userId): bool {
        return $this->check('ai:' . $userId, 'ai_minute');
    }

    /**
     * 设置自定义限制。
     */
    public function setLimit(string $type, int $max, int $window): void {
        $this->limits[$type] = ['max' => $max, 'window' => $window];
    }
}

/**
 * Linked3 Audit Logger — v5.7.0.5
 * 审计日志: 所有敏感操作记录到 DB
 */
class Linked3_Audit_Logger {
    private static ?Linked3_Audit_Logger $instance = null;

    public static function instance(): Linked3_Audit_Logger {
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

        $wpdb->insert($table, [
            'user_id' => get_current_user_id(),
            'action' => $action,
            'details' => wp_json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'time' => current_time('mysql'),
        ]);
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
        return $wpdb->get_results($query, ARRAY_A);
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
class Linked3_Security_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('security.validator', fn() => Linked3_Security_Validator::instance());
        $container->set('security.rate_limiter', fn() => Linked3_Rate_Limiter_V2::instance());
        $container->set('security.async_queue', fn() => Linked3_Async_Queue::instance());
        $container->set('security.audit', fn() => Linked3_Audit_Logger::instance());

        // 监听安全违规
        linked3_subscribe('linked3.security.violation', function(Linked3_Event $evt) {
            linked3_container()->get('logger')->warning('Security violation', $evt->getPayload());
        });

        // 监听速率限制
        linked3_subscribe('linked3.rate_limited.exceeded', function(Linked3_Event $evt) {
            linked3_container()->get('logger')->warning('Rate limited', $evt->getPayload());
        });

        linked3_dispatch('linked3.security.boot', ['version' => LINKED3_VERSION]);
    }
}
