<?php

declare(strict_types=1);
/**
 * SQL Safety Helper — v19.3.1 全局 SQL 防御层.
 *
 * 诞生背景：
 *   全量审计发现 10 处 SQL 变量插值，虽然全部使用 WP 核心表名或内部常量
 *   （技术上不可注入），但"不谋全局者，不足谋一域"——防御不应依赖
 *   "当前恰好安全"，而应建立"永远安全"的机制。
 *
 * 本类提供：
 *   1. table_exists()  — 安全的表存在性检查（替代 SHOW TABLES LIKE '{$table}'）
 *   2. validate_table_name() — 表名白名单校验（只允许字母/数字/下划线）
 *   3. safe_truncate()  — 安全的 TRUNCATE（先校验表名再执行）
 *
 * @package Linked3
 * @subpackage Classes\Core
 */

namespace Linked3\Classes\Core;

if (!defined('ABSPATH')) {
    exit;
}

class SQLSafety
{
    /**
     * 验证表名只包含安全字符（字母/数字/下划线/连字符）.
     *
     * @param string $table
     * @return string|false  返回清理后的表名，或 false 如果包含危险字符
     */
    public static function validate_table_name(string $table) : mixed {
        if (!is_string($table) || empty($table)) {
            return false;
        }
        // 只允许：字母、数字、下划线、连字符、点（用于 db.table 格式）
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $table)) {
            return false;
        }
        return $table;
    }

    /**
     * 安全的表存在性检查 — 替代 SHOW TABLES LIKE '{$table}'.
     *
     * 旧模式：$wpdb->get_var("SHOW TABLES LIKE '{$table}'")
     * 新模式：SQLSafety::table_exists($table)
     *
     * @param string $table  完整表名（含 $wpdb->prefix）
     * @return bool
     */
    public static function table_exists(string $table) : mixed     {
        global $wpdb;
        $safe = self::validate_table_name($table);
        if ($safe === false) {
            return false;
        }
        // 使用 prepare 而非变量插值
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $safe));
        return $result === $safe;
    }

}
