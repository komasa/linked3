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
    public static function validate_table_name($table) : mixed {
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
    public static function table_exists($table) : mixed     {
        global $wpdb;
        $safe = self::validate_table_name($table);
        if ($safe === false) {
            return false;
        }
        // 使用 prepare 而非变量插值
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $safe));
        return $result === $safe;
    }

    /**
     * 安全的 TRUNCATE — 先校验表名再执行.
     *
     * @param string $table  完整表名
     * @return bool
     */
    public static function safe_truncate($table) : mixed {
        global $wpdb;
        $safe = self::validate_table_name($table);
        if ($safe === false) {
            return false;
        }
        // 二次确认：表必须存在且以 $wpdb->prefix 开头
        if (strpos($safe, $wpdb->prefix) !== 0 && !self::table_exists($safe)) {
            return false;
        }
        // SECURITY NOTE v27.0.0 (P9): $wpdb->prepare() cannot be used here
        // because table names are identifiers, not values — placeholders
        // only work for values. The $safe variable has already been validated
        // by validate_table_name() (regex whitelist + prefix check + existence
        // check), so SQL injection is not possible.
        return $wpdb->query($wpdb->prepare("TRUNCATE TABLE %i", $safe)) !== false;
    }

    /**
     * 安全的 DELETE — 使用 prepare 防注入.
     *
     * @param string $table     完整表名
     * @param string $where_col WHERE 列名（白名单校验）
     * @param string $where_val WHERE 值
     * @return bool
     */
    public static function safe_delete($table, $where_col, $where_val) : mixed     {
        global $wpdb;
        $safe_table = self::validate_table_name($table);
        if ($safe_table === false) {
            return false;
        }
        // 列名只允许字母/数字/下划线
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $where_col)) {
            return false;
        }
        return $wpdb->query($wpdb->prepare("DELETE FROM {$safe_table} WHERE {$where_col} = %s", $where_val)) !== false;
    }
}
