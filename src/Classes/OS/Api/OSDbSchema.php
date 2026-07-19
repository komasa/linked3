<?php

declare(strict_types=1);
/**
 * Linked3 OSDbSchema 15.0.0-rc9
 *
 * 数据库表创建
 *
 * 表: linked3_reverse_results / ruliu_progress / quality_reports
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc9
 * @version 15.0.0-rc9
 */

namespace Linked3\Classes\OS\Api;

/**
 * OS Module — OS DB Schema
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Api/class-linked3-v18-db-schema.php
 * Original class: V18_Db_Schema
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSDbSchema {


    /**
     * 数据库表定义
     */
    const TABLES = [
        'reverse_results' => 'linked3_v18_reverse_results',
        'ruliu_progress' => 'linked3_v18_ruliu_progress',
        'quality_reports' => 'linked3_v18_quality_reports',
    ];

    /**
     * 创建数据库表
     *
     * v16.0.5: Now delegates to the main Schema::create_all().
     * The V18 table definitions have been consolidated into the main
     * Schema class to ensure they are created during activation alongside
     * all other plugin tables, with proper SQLite/Playground compatibility.
     */
    public static function create_tables(): void {
        if (class_exists('Linked3\\Includes\\DB\\Schema')) {
            // Re-run create_all() — it's idempotent via dbDelta.
            \Linked3\Includes\DB\Schema::create_all();
        }
        update_option('linked3_v18_db_version', '15.0.0');
    }

    /**
     * 保存逆向结果
     */
    public static function save_reverse_result(string $engineer_type, string $target, array $result, int $score = 0): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLES['reverse_results'];
        
        $wpdb->insert($table, [
            'engineer_type' => $engineer_type,
            'target_hash' => md5($target),
            'target_description' => $target,
            'result_json' => wp_json_encode($result),
            'validation_score' => $score,
        ], ['%s', '%s', '%s', '%s', '%d']);
        
        return $wpdb->insert_id;
    }

    /**
     * 获取逆向结果
     */
    public static function get_reverse_result(int $id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLES['reverse_results'];
        
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$row) return null;
        
        $row['result_json'] = json_decode($row['result_json'], true);
        return $row;
    }

    /**
     * 保存入流进度
     */
    public static function save_ruliu_progress(int $day, string $state, float $progress, array $metrics = [], string $notes = ''): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLES['ruliu_progress'];
        
        $wpdb->insert($table, [
            'day_number' => $day,
            'state' => $state,
            'progress_pct' => $progress,
            'metrics_json' => wp_json_encode($metrics),
            'notes' => $notes,
        ], ['%d', '%s', '%f', '%s', '%s']);
        
        return $wpdb->insert_id;
    }

    /**
     * 保存质量报告
     */
    public static function save_quality_report(string $target_type, string $target, float $score, array $gate_results, array $suggestions = []): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLES['quality_reports'];
        
        $wpdb->insert($table, [
            'target_type' => $target_type,
            'target_hash' => md5($target),
            'overall_score' => $score,
            'gate_results_json' => wp_json_encode($gate_results),
            'suggestions_json' => wp_json_encode($suggestions),
        ], ['%s', '%s', '%f', '%s', '%s']);
        
        return $wpdb->insert_id;
    }

    /**
     * 注册
     */
    public static function register(): void {
        add_action('init', [__CLASS__, 'maybe_create_tables']);
    }

    /**
     * 按需创建表
     */
    public static function maybe_create_tables(): void {
        $db_version = get_option('linked3_v18_db_version', '0');
        if (version_compare($db_version, '15.0.0', '<')) {
            self::create_tables();
        }
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '15.0.0-rc9',
            'title' => '数据库表创建',
            'tables' => array_values(self::TABLES),
            'db_version' => get_option('linked3_v18_db_version', '0'),
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Api\OSDbSchema')) {
    add_action('init', ['\Linked3\Classes\OS\Api\OSDbSchema', 'register'], 10);
}
