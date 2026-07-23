<?php

declare(strict_types=1);
/**
 * Linked3 OSDashboard 15.0.0-rc1
 *
 * V18管理后台仪表盘
 *
 * 10个模块状态总览+健康检查面板
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc1
 * @version 15.0.0-rc1
 */

namespace Linked3\Classes\OS\Admin;

/**
 * OS Module — OS Dashboard
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Admin/V18Dashboard.php
 * Original class: OSDashboard
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSDashboard {


    /**
     * 渲染仪表盘页面
     */
    public static function render_dashboard(): string {
        $modules = self::get_module_status();
        $health = self::run_health_check();
        
        $html = '<div class="wrap linked3-v18-dashboard">';
        $html .= '<h1>V18方法论集成仪表盘 <span class="version">v15.0.0</span></h1>';
        $html .= '<div class="health-status">' . self::render_health($health) . '</div>';
        $html .= '<div class="modules-grid">' . self::render_modules($modules) . '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 获取所有模块状态
     */
    public static function get_module_status(): array {
        $modules = [
            'v12.0.0' => 'OSReverseEngine',
            'v12.1.0' => 'OSCapabilityLock',
            'v12.2.0' => 'OSVisualAnalytics',
            'v12.3.0' => 'OSConsciousnessLayer',
            'v12.4.0' => 'OSOnboardingTracker',
            'v12.5.0' => 'OSEngineerRegistry',
            'v12.6.0' => 'OSTextCreation',
            'v12.7.0' => 'OSMomentumFlywheel',
            'v12.8.0' => 'OSCapabilityStages',
            'v12.9.0' => 'OSQualityGate',
            'v14.0.0' => 'OSReverseAjax',
            'v14.1.0' => 'OSCapabilityLockAjax',
            'v14.2.0' => 'OSVisualAnalyticsAjax',
            'v14.3.0' => 'OSConsciousnessAjax',
            'v14.4.0' => 'OSOnboardingAjax',
            'v14.5.0' => 'OSEngineerRegistryAjax',
            'v14.6.0' => 'OSTextCreationAjax',
            'v14.7.0' => 'OSMomentumAjax',
            'v14.8.0' => 'OSCapabilityStagesAjax',
            'v14.9.0' => 'OSQualityGateAjax',
        ];
        
        $status = [];
        foreach ($modules as $ver => $class) {
            $status[] = [
                'version' => $ver,
                'class' => $class,
                'loaded' => class_exists($class),
                'has_version_info' => class_exists($class) && method_exists($class, 'get_version_info'),
            ];
        }
        return $status;
    }

    /**
     * 运行健康检查
     */
    public static function run_health_check(): array {
        if (class_exists('\Linked3\Classes\OS\Admin\OSIntegrationHub')) {
            return OSIntegrationHub::health_check();
        }
        return ['error' => 'Integration Hub not loaded'];
    }

    /**
     * 渲染健康状态
     */
    private static function render_health(array $health): string {
        $html = '<div class="health-card">';
        $html .= '<h2>系统健康</h2>';
        foreach ($health as $key => $value) {
            if (is_array($value)) {
                $html .= '<div class="health-item"><strong>' . esc_html($key) . ':</strong> ' . count($value) . ' 项</div>';
            } else {
                $html .= '<div class="health-item"><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</div>';
            }
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * 渲染模块网格
     */
    private static function render_modules(array $modules): string {
        $html = '';
        foreach ($modules as $mod) {
            $status_class = $mod['loaded'] ? 'loaded' : 'not-loaded';
            $status_icon = $mod['loaded'] ? '✓' : '✗';
            $html .= '<div class="module-card ' . $status_class . '">';
            $html .= '<div class="module-version">' . esc_html($mod['version']) . '</div>';
            $html .= '<div class="module-class">' . esc_html($mod['class']) . '</div>';
            $html .= '<div class="module-status">' . $status_icon . '</div>';
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * 注册admin菜单
     *
     * v16.0.3: REMOVED separate top-level menu registration.
     * V18 is now accessed via the main dashboard's "V18实验室" tab
     * (?page=linked3-dashboard&tab=v18). This eliminates the
     * "Sorry, you are not allowed to access this page" error caused
     * by slug conflict between 'linked3-v18' and 'linked3-dashboard'.
     *
     * The render_dashboard() method is still available for the
     * tab-v18.php partial to call.
     */
    public static function register_admin_menu(): void {
        // No-op — V18 is rendered as a tab in the main dashboard.
        // See: admin/views/dashboard/partials/tab-v18.php
    }

    /**
     * 注册
     */
    public static function register(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '15.0.0-rc1',
            'title' => 'V18管理后台仪表盘',
            'desc' => '10个模块状态总览+健康检查面板',
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Admin\OSDashboard')) {
    add_action('init', ['\Linked3\Classes\OS\Admin\OSDashboard', 'register'], 10);
}
