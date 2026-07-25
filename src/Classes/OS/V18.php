<?php

declare(strict_types=1);
/**
 * Linked3 V18 Facade v16.0.0
 *
 * V18子系统统一入口 — Facade模式
 *
 * 来源: v16.0.0全量重铸方案J（C+H混合）
 *
 * 设计原理:
 *   - Facade模式: 统一入口，隐藏子系统复杂性
 *   - 单一入口: 所有V18功能通过 V18::xxx() 调用
 *   - 懒加载: 按需加载子模块，不预加载全部
 *   - 向后兼容: 保留原类名直接调用能力
 *
 * 调用示例:
 *   // 逆向拆解
 *   $result = V18::reverse_parse($json, 'visual_system');
 *
 *   // 能知约束
 *   $constraint = V18::neng_constraint('T1');
 *
 *   // SVG统计
 *   $stats = V18::svg_stats('D08');
 *
 *   // 健康检查
 *   $health = V18::health_check();
 *
 * @package Linked3\Classes\OS
 * @since 16.0.0
 * @version 16.0.0
 */

namespace Linked3\Classes\OS;

/**
 * OS Module — OS Facade
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/V18Facade.php
 * Original class: OSFacade (legacy prefixed name, now migrated to V8)
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class V18 {

    /**
     * 子系统映射表
     * v27.9.0 (P0-D): 裸类名 → FQCN, 修复 class_exists 永远返回 false
     */
    private static $module_map = [
        // Core模块
        'reverse_engine'        => '\\Linked3\\Classes\\OS\\Core\\OSReverseEngine',
        'reverse_dimensions'    => '\\Linked3\\Classes\\OS\\Core\\OSReverseDimensions',
        'reverse_registry'      => '\\Linked3\\Classes\\OS\\Core\\OSEngineerRegistry',
        'reverse_quality_gate'  => '\\Linked3\\Classes\\OS\\Core\\OSQualityGate',
        'reverse_text'          => '\\Linked3\\Classes\\OS\\Core\\OSTextCreation',
        'neng_suo'              => '\\Linked3\\Classes\\OS\\Core\\OSCapabilityLock',
        'three_layer'           => '\\Linked3\\Classes\\OS\\Core\\OSConsciousnessLayer',
        'ru_liu'                => '\\Linked3\\Classes\\OS\\Core\\OSOnboardingTracker',
        'hong_liu'              => '\\Linked3\\Classes\\OS\\Core\\OSMomentumFlywheel',
        'neng_zhi'              => '\\Linked3\\Classes\\OS\\Core\\OSCapabilityStages',
        'svg_stats'             => '\\Linked3\\Classes\\OS\\Core\\OSVisualAnalytics',
        // 集成中心
        'hub'                   => '\\Linked3\\Classes\\OS\\Api\\OSIntegrationHubV2',
        // Admin
        'dashboard'             => '\\Linked3\\Classes\\OS\\Admin\\OSDashboard',
        // API
        'rest_api'              => '\\Linked3\\Classes\\OS\\Api\\OSRestApi',
        'db_schema'             => '\\Linked3\\Classes\\OS\\Api\\OSDbSchema',
    ];

    /**
     * 已加载模块缓存
     */
    private static $loaded = [];

    /**
     * 获取模块实例（懒加载）
     */
    public static function module(string $name) : null {
        if (!isset(self::$module_map[$name])) {
            return null;
        }

        $class = self::$module_map[$name];

        if (!isset(self::$loaded[$name])) {
            if (class_exists($class)) {
                self::$loaded[$name] = $class;
            } else {
                return null;
            }
        }

        return self::$loaded[$name];
    }

    // ================================================================
    // 逆向引擎 (OSReverseEngine)
    // ================================================================

    /**
     * 逆向解析
     */
    public static function reverse_parse(string $json, string $type = '') : mixed {
        $cls = self::module('reverse_engine');
        return $cls ? call_user_func([$cls, 'reverse_parse'], $json, $type) : null;
    }

    /**
     * 逆向转SEED
     */
    public static function reverse_to_seed(array $parsed) {
        $cls = self::module('reverse_engine');
        return $cls ? call_user_func([$cls, 'reverse_to_seed'], $parsed) : null;
    }

    /**
     * 逆向对比
     */
    public static function reverse_compare(array $a, array $b) {
        $cls = self::module('reverse_engine');
        return $cls ? call_user_func([$cls, 'reverse_compare'], $a, $b) : null;
    }

    /**
     * 构建逆向Prompt
     */
    public static function reverse_prompt(string $type, string $desc) {
        $cls = self::module('reverse_dimensions');
        return $cls ? call_user_func([$cls, 'build_reverse_prompt'], $type, $desc) : null;
    }

    // ================================================================
    // 能所结构 (OSCapabilityLock)
    // ================================================================

    /**
     * 构建能知约束
     */
    public static function neng_constraint(string $state, string $mode = 'reading', string $exp = 'answer') {
        $cls = self::module('neng_suo');
        return $cls ? call_user_func([$cls, 'build_neng_constraint'], $state, $mode, $exp) : null;
    }

    // ================================================================
    // SVG统计 (OSVisualAnalytics)
    // ================================================================

    /**
     * 按图示类型获取SVG统计
     */
    public static function svg_stats(string $chart_type) {
        $cls = self::module('svg_stats');
        return $cls ? call_user_func([$cls, 'get_stats_by_chart_type'], $chart_type) : null;
    }

    /**
     * 预测原子数量
     */
    public static function svg_predict(string $chart_type) {
        $cls = self::module('svg_stats');
        return $cls ? call_user_func([$cls, 'predict_atom_count'], $chart_type) : null;
    }

    // ================================================================
    // 三层能观 (OSConsciousnessLayer)
    // ================================================================

    /**
     * 分配频率标注
     */
    public static function assign_frequency(string $content) {
        $cls = self::module('three_layer');
        return $cls ? call_user_func([$cls, 'assign_frequency'], $content) : null;
    }

    /**
     * 构建Badge标注
     */
    public static function badge(string $module_id, string $freq) {
        $cls = self::module('three_layer');
        return $cls ? call_user_func([$cls, 'build_badge_annotation'], $module_id, $freq) : null;
    }

    // ================================================================
    // 入流追踪 (OSOnboardingTracker)
    // ================================================================

    /**
     * 计算入流状态进度
     */
    public static function ruliu_progress(int $day) {
        $cls = self::module('ru_liu');
        return $cls ? call_user_func([$cls, 'calculate_state_progress'], $day) : null;
    }

    // ================================================================
    // 洪流飞轮 (OSMomentumFlywheel)
    // ================================================================

    /**
     * 计算飞轮分数
     */
    public static function flywheel_score(array $factors) {
        $cls = self::module('hong_liu');
        return $cls ? call_user_func([$cls, 'calculate_flywheel_score'], $factors) : null;
    }

    // ================================================================
    // 能知三阶 (OSCapabilityStages)
    // ================================================================

    /**
     * 能知三阶→内容类型映射
     */
    public static function nengzhi_map(string $stage) {
        $cls = self::module('neng_zhi');
        return $cls ? call_user_func([$cls, 'map_to_content_type'], $stage) : null;
    }

    // ================================================================
    // 质量门禁 (OSQualityGate)
    // ================================================================

    /**
     * 质量检查
     */
    public static function quality_check(array $data) {
        $cls = self::module('reverse_quality_gate');
        return $cls ? call_user_func([$cls, 'check_quality'], $data) : null;
    }

    // ================================================================
    // 集成中心 (OSIntegrationHubV2)
    // ================================================================

    /**
     * 健康检查
     */
    public static function health_check() {
        $cls = self::module('hub');
        return $cls ? call_user_func([$cls, 'health_check']) : null;
    }

    /**
     * 运行全量流水线
     */
    public static function run_pipeline(array $input) {
        $cls = self::module('hub');
        return $cls ? call_user_func([$cls, 'run_full_pipeline_v2'], $input) : null;
    }

    /**
     * 获取版本信息
     */
    public static function version_info() {
        $cls = self::module('hub');
        return $cls ? call_user_func([$cls, 'get_version_info']) : [
            'facade_version' => '16.0.0',
            'hub_available' => false,
        ];
    }

    // ================================================================
    // 系统注册
    // ================================================================

    /**
     * 注册V18子系统到WordPress
     * 由 Hook Manager 调用
     *
     * Note: register_all() is an alias for register() — the Hook Manager
     * calls register_all() (v16.0.0 naming), while the Facade originally
     * exposed register(). We provide both to avoid a "method does not exist"
     * fatal when init fires.
     */
    public static function register_all() : void {
        static::register();
    }

    /**
     * 注册V18子系统到WordPress
     * 由 Hook Manager 调用
     */
    public static function register() : void {
        // 注册AJAX (10个)
        $ajax_classes = [
            '\\Linked3\\Classes\\OS\\Ajax\\OSReverseAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSCapabilityLockAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSVisualAnalyticsAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSConsciousnessAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSOnboardingAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSEngineerRegistryAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSTextCreationAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSMomentumAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSCapabilityStagesAjax',
            '\\Linked3\\Classes\\OS\\Ajax\\OSQualityGateAjax',
        ];

        foreach ($ajax_classes as $cls) {
            if (class_exists($cls) && method_exists($cls, 'register')) {
                call_user_func([$cls, 'register']);
            }
        }

        // 注册REST API
        if (class_exists('\Linked3\Classes\OS\OSRestApi') && method_exists('\Linked3\Classes\OS\OSRestApi', 'register')) {
            call_user_func(['\\Linked3\\Classes\\OS\\Api\\OSRestApi', 'register']);
        }

        // 注册短代码
        if (class_exists('\Linked3\Classes\OS\OSShortcodes') && method_exists('\Linked3\Classes\OS\OSShortcodes', 'register')) {
            call_user_func(['\\Linked3\\Classes\\OS\\Api\\OSShortcodes', 'register']);
        }

        // 注册Widget
        if (class_exists('\Linked3\Classes\OS\OSWidget') && method_exists('\Linked3\Classes\OS\OSWidget', 'register')) {
            call_user_func(['\\Linked3\\Classes\\OS\\Api\\OSWidget', 'register']);
        }

        // 注册Admin面板
        if (class_exists('\Linked3\Classes\OS\OSDashboard') && method_exists('\Linked3\Classes\OS\OSDashboard', 'register')) {
            call_user_func(['\\Linked3\\Classes\\OS\\Admin\\OSDashboard', 'register']);
        }

        // 注册DB Schema (激活时创建表)
        if (class_exists('\Linked3\Classes\OS\OSDbSchema') && method_exists('\Linked3\Classes\OS\OSDbSchema', 'register')) {
            call_user_func(['OSDbSchema', 'register']);
        }
    }

    /**
     * 获取Facade版本信息
     */
    public static function get_facade_info(): array {
        return [
            'facade_version' => '16.0.0',
            'design_pattern' => 'Facade',
            'module_count' => count(self::$module_map),
            'loaded_count' => count(self::$loaded),
            'subsystem' => 'src/Classes/V18/',
            'structure' => [
                'Core/' => '11个核心模块',
                'Ajax/' => '10个AJAX接口',
                'Admin/' => '4个管理面板',
                'Api/' => '7个API/集成模块',
            ],
        ];
    }
}
