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
     */
    private static $module_map = [
        // Core模块
        'reverse_engine'        => 'OSReverseEngine',
        'reverse_dimensions'    => 'OSReverseDimensions',
        'reverse_registry'      => 'OSEngineerRegistry',
        'reverse_quality_gate'  => 'OSQualityGate',
        'reverse_text'          => 'OSTextCreation',
        'neng_suo'              => 'OSCapabilityLock',
        'three_layer'           => 'OSConsciousnessLayer',
        'ru_liu'                => 'OSOnboardingTracker',
        'hong_liu'              => 'OSMomentumFlywheel',
        'neng_zhi'              => 'OSCapabilityStages',
        'svg_stats'             => 'OSVisualAnalytics',
        // 集成中心
        'hub'                   => 'OSIntegrationHubV2',
        // Admin
        'dashboard'             => 'OSDashboard',
        // API
        'rest_api'              => 'OSRestApi',
        'db_schema'             => 'OSDbSchema',
    ];

    /**
     * 已加载模块缓存
     */
    private static $loaded = [];

    /**
     * 获取模块实例（懒加载）
     */
    public static function module(string $name) : mixed {
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
     * 逆向校验
     */
    public static function reverse_validate(array $parsed, string $type = '') {
        $cls = self::module('reverse_engine');
        return $cls ? call_user_func([$cls, 'reverse_validate'], $parsed, $type) : null;
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

    /**
     * 根据内容类型推导能知约束
     */
    public static function neng_from_content_type(string $type) {
        $cls = self::module('neng_suo');
        return $cls ? call_user_func([$cls, 'derive_from_content_type'], $type) : null;
    }

    /**
     * 注入能知约束到Prompt
     */
    public static function neng_inject(string $prompt, array $constraint) {
        $cls = self::module('neng_suo');
        return $cls ? call_user_func([$cls, 'inject_into_prompt'], $prompt, $constraint) : null;
    }

    // ================================================================
    // SVG统计 (OSVisualAnalytics)
    // ================================================================

    /**
     * 获取SVG统计基线
     */
    public static function svg_baseline() {
        $cls = self::module('svg_stats');
        return $cls ? call_user_func([$cls, 'get_baseline']) : null;
    }

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
     * 获取100天计划
     */
    public static function ruliu_plan() {
        $cls = self::module('ru_liu');
        return $cls ? call_user_func([$cls, 'get_100day_plan']) : null;
    }

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

    /**
     * 飞轮改进建议
     */
    public static function flywheel_suggest(array $factors) {
        $cls = self::module('hong_liu');
        return $cls ? call_user_func([$cls, 'suggest_improvement'], $factors) : null;
    }

    // ================================================================
    // 能知三阶 (OSCapabilityStages)
    // ================================================================

    /**
     * 能知三阶自动检测
     */
    public static function nengzhi_detect(string $content) {
        $cls = self::module('neng_zhi');
        return $cls ? call_user_func([$cls, 'auto_detect_stage'], $content) : null;
    }

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

    /**
     * 质量报告
     */
    public static function quality_report(array $data) {
        $cls = self::module('reverse_quality_gate');
        return $cls ? call_user_func([$cls, 'generate_quality_report'], $data) : null;
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
     * 获取所有模块
     */
    public static function all_modules() {
        $cls = self::module('hub');
        return $cls ? call_user_func([$cls, 'get_all_modules']) : null;
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
            'OSReverseAjax',
            'OSCapabilityLockAjax',
            'OSVisualAnalyticsAjax',
            'OSConsciousnessAjax',
            'OSOnboardingAjax',
            'OSEngineerRegistryAjax',
            'OSTextCreationAjax',
            'OSMomentumAjax',
            'OSCapabilityStagesAjax',
            'OSQualityGateAjax',
        ];

        foreach ($ajax_classes as $cls) {
            if (class_exists($cls) && method_exists($cls, 'register')) {
                call_user_func([$cls, 'register']);
            }
        }

        // 注册REST API
        if (class_exists('\Linked3\Classes\OS\OSRestApi') && method_exists('\Linked3\Classes\OS\OSRestApi', 'register')) {
            call_user_func(['OSRestApi', 'register']);
        }

        // 注册短代码
        if (class_exists('\Linked3\Classes\OS\OSShortcodes') && method_exists('\Linked3\Classes\OS\OSShortcodes', 'register')) {
            call_user_func(['OSShortcodes', 'register']);
        }

        // 注册Widget
        if (class_exists('\Linked3\Classes\OS\OSWidget') && method_exists('\Linked3\Classes\OS\OSWidget', 'register')) {
            call_user_func(['OSWidget', 'register']);
        }

        // 注册Admin面板
        if (class_exists('\Linked3\Classes\OS\OSDashboard') && method_exists('\Linked3\Classes\OS\OSDashboard', 'register')) {
            call_user_func(['OSDashboard', 'register']);
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
