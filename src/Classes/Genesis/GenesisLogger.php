<?php

declare(strict_types=1);
/**
 * Linked3 Genesis 结构化日志 v10.0.1
 *
 * 公理1实现: 日志信息熵减 — 从散落 error_log 收敛到结构化日志表
 * 解决熵增点E8: 日志散落 error_log, 无法按 job 查询
 *
 * 日志表: {prefix}linked3_genesis_log
 * 字段: id, job_id, stage, level, message, context(JSON), created_at
 * 级别: DEBUG < INFO < WARN < ERROR < FATAL
 * 保留: 默认7天, WP cron 自动清理
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @version 10.0.1
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisLogger {

    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO  = 'INFO';
    const LEVEL_WARN  = 'WARN';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_FATAL = 'FATAL';

    /** @var string 日志表名 */
    private static $table;

    /** @var bool 表是否已创建 */
    private static $table_ready = false;

    /** @var int 当前 job_id (链路追踪) */
    private static $current_job_id = null;

    /** @var bool 是否启用DEBUG日志 */
    private static $debug_enabled = null;

    /**
     * 初始化
     */
    public static function init() : void {
        global $wpdb;
        self::$table = $wpdb->prefix . 'linked3_genesis_log';

        // 检查表是否存在, 不存在则创建 (延迟创建, 首次写入时)
        if (self::$debug_enabled === null) {
            self::$debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || (get_option('linked3_genesis_debug_log', '0') === '1');
        }

        // 注册cron清理
        if (!wp_next_scheduled('linked3_genesis_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'linked3_genesis_log_cleanup');
        }
        add_action('linked3_genesis_log_cleanup', [__CLASS__, 'cleanup_old_logs']);
    }

    /**
     * 创建日志表 (首次写入时延迟创建)
     */
    private static function ensure_table() : mixed {
        if (self::$table_ready) return true;
        global $wpdb;
        $table = self::$table;
        $charset_collate = $wpdb->get_charset_collate();

        // 检查表是否存在
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
            self::$table_ready = true;
            return true;
        }

        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id VARCHAR(64) NOT NULL DEFAULT '',
            stage VARCHAR(64) NOT NULL DEFAULT '',
            level VARCHAR(16) NOT NULL DEFAULT 'INFO',
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_job (job_id),
            KEY idx_stage (stage),
            KEY idx_level (level),
            KEY idx_created (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        self::$table_ready = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table);
        return self::$table_ready;
    }

    /**
     * 设置当前 job_id (链路追踪)
     */
    public static function set_job_id($job_id) : void {
        self::$current_job_id = $job_id;
    }

    public static function get_job_id() : mixed {
        return self::$current_job_id;
    }

    /**
     * 写日志
     */
    public static function log($level, $message, $context = [], $stage = '') : void {
        // DEBUG 级别需要显式开启
        if ($level === self::LEVEL_DEBUG && !self::$debug_enabled) {
            return;
        }

        if (!self::ensure_table()) {
            // 表创建失败, 降级到 error_log
            error_log('[Linked3 Genesis] [' . $level . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
            return;
        }

        global $wpdb;
        $wpdb->insert(self::$table, [
            'job_id'     => self::$current_job_id ?: '',
            'stage'      => $stage ?: '',
            'level'      => $level,
            'message'    => is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE),
            'context'    => !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s']);
    }

    public static function debug($message, $context = [], $stage = '') : void {
        self::log(self::LEVEL_DEBUG, $message, $context, $stage);
    }

    public static function info($message, $context = [], $stage = '') : void {
        self::log(self::LEVEL_INFO, $message, $context, $stage);
    }

    public static function warn($message, $context = [], $stage = '') : void {
        self::log(self::LEVEL_WARN, $message, $context, $stage);
    }

    public static function error($message, $context = [], $stage = '') : void {
        self::log(self::LEVEL_ERROR, $message, $context, $stage);
        // ERROR 级别同时写 error_log (便于服务器监控)
        error_log('[Linked3 Genesis ERROR] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    public static function fatal($message, $context = [], $stage = '') : void {
        self::log(self::LEVEL_FATAL, $message, $context, $stage);
        error_log('[Linked3 Genesis FATAL] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 记录链路步骤 (带耗时)
     */
    public static function stage($stage, $message, $context = [], $elapsed_ms = null) : void {
        if ($elapsed_ms !== null) {
            $context = array_merge($context, ['elapsed_ms' => $elapsed_ms]);
        }
        self::info($message, $context, $stage);
    }

    /**
     * 记录异常
     */
    public static function exception($e, $stage = '', $extra_context = []) : void {
        if ($e instanceof Linked3_Genesis_Exception) {
            $context = array_merge($e->getContext(), $extra_context, [
                'error_code' => $e->getErrorCode(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);
            $level = self::LEVEL_ERROR;
            $message = '[' . $e->getErrorCode() . '] ' . $e->getMessage();
        } else if ($e instanceof \Throwable) {
            $context = array_merge($extra_context, [
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $level = self::LEVEL_ERROR;
            $message = $e->getMessage();
        } else {
            $context = $extra_context;
            $level = self::LEVEL_ERROR;
            $message = is_string($e) ? $e : '未知异常';
        }
        self::log($level, $message, $context, $stage);
    }

    /**
     * 查询日志 (按 job_id)
     */
    public static function get_by_job($job_id, $level = null, $limit = 500) : mixed {
        if (!self::ensure_table()) return [];
        global $wpdb;
        $sql = "SELECT * FROM " . self::$table . " WHERE job_id = %s";
        $params = [$job_id];
        if ($level) {
            $sql .= " AND level = %s";
            $params[] = $level;
        }
        $sql .= " ORDER BY id ASC LIMIT %d";
        $params[] = intval($limit);
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * 查询日志 (按时间范围)
     */
    public static function get_recent($hours = 24, $level = null, $limit = 1000) : mixed {
        if (!self::ensure_table()) return [];
        global $wpdb;
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $cutoff = date('Y-m-d H:i:s', time() - intval($hours) * HOUR_IN_SECONDS);
        $sql = "SELECT * FROM " . self::$table . " WHERE created_at >= %s";
        $params = [$cutoff];
        if ($level) {
            $sql .= " AND level = %s";
            $params[] = $level;
        }
        $sql .= " ORDER BY id DESC LIMIT %d";
        $params[] = intval($limit);
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * 获取统计 (按级别)
     */
    public static function get_stats($hours = 24) : mixed {
        if (!self::ensure_table()) return [];
        global $wpdb;
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $cutoff = date('Y-m-d H:i:s', time() - intval($hours) * HOUR_IN_SECONDS);
        $sql = "SELECT level, COUNT(*) as cnt FROM " . self::$table .
               " WHERE created_at >= %s GROUP BY level";
        $results = $wpdb->get_results($wpdb->prepare($sql, $cutoff));
        $stats = [];
        foreach ($results as $r) {
            $stats[$r->level] = intval($r->cnt);
        }
        return $stats;
    }

    /**
     * 清理过期日志 (cron 调用)
     */
    public static function cleanup_old_logs() : void {
        if (!self::ensure_table()) return;
        global $wpdb;
        $days = intval(get_option('linked3_genesis_log_retention_days', 7));
        $days = max(1, $days);
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $cutoff = date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::$table . " WHERE created_at < %s",
            $cutoff
        ));
    }

    /**
     * 清空所有日志 (管理操作)
     */
    public static function clear_all() : mixed {
        if (!self::ensure_table()) return false;
        global $wpdb;
        // v19.3.1: 使用 SQL Safety 工具校验表名
        $safe_table = \Linked3\Classes\Core\SQLSafety::validate_table_name(self::$table);
        if ($safe_table === false) return false;
        // SECURITY NOTE v27.0.0 (P9): $safe_table is validated by
        // SQLSafety::validate_table_name() (regex whitelist + prefix
        // check). Table names are identifiers and cannot use $wpdb->prepare()
        // placeholders (those only work for values). Safe to interpolate.
        return $wpdb->query("TRUNCATE TABLE " . $safe_table) !== false;
    }

    /**
     * 耗时测量工具
     */
    public static function timer($label = '') : mixed {
        return new class($label) {
            private $label;
            private $start;
            public function __construct($label) {
                $this->label = $label;
                $this->start = microtime(true);
            }
            public function elapsed_ms() : mixed {
                return (int)((microtime(true) - $this->start) * 1000);
            }
            public function stop($stage = '', $context = []) : mixed {
                $ms = $this->elapsed_ms();
                GenesisLogger::stage(
                    $stage,
                    $this->label . ' 完成',
                    $context,
                    $ms
                );
                return $ms;
            }
        };
    }
}

// 初始化
add_action('init', ['\Linked3\Classes\Genesis\GenesisLogger', 'init'], 5);
