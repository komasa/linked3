<?php
/**
 * Linked3 Linked3_OS_Onboarding_Panel 15.0.0-rc4
 *
 * 入流追踪进度面板
 *
 * 100天进度可视化
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc4
 * @version 15.0.0-rc4
 */

namespace Linked3\Classes\OS\Admin;

/**
 * OS Module — Onboarding Panel
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Admin/class-linked3-v18-ruliu-panel.php
 * Original class: Linked3_V18_Ruliu_Panel
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class Linked3_OS_Onboarding_Panel {


    /**
     * 渲染面板
     */
    public static function render_panel(): string {
        $html = '<div class="wrap linked3-v18-panel">';
        $html .= '<h1>入流追踪进度面板 <span class="version">v15.0.0</span></h1>';
        $html .= '<p>100天进度可视化</p>';
        $html .= '<div class="panel-content">' . self::render_content() . '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 渲染内容
     * v16.0.12: Replaced placeholder with actual ruliu tracking guide
     */
    private static function render_content(): string {
        return '<div class="v18-ruliu-guide">
            <h4>入流四状态追踪体系</h4>
            <p>基于李善友方法论，100天起号全流程追踪体系:</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>状态</th>
                        <th>阶段</th>
                        <th>核心指标</th>
                        <th>持续时间</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>看见</strong></td>
                        <td>曝光获取</td>
                        <td>展现量、点击率</td>
                        <td>第1-25天</td>
                    </tr>
                    <tr>
                        <td><strong>相信</strong></td>
                        <td>信任建立</td>
                        <td>互动率、关注转化</td>
                        <td>第26-50天</td>
                    </tr>
                    <tr>
                        <td><strong>承担</strong></td>
                        <td>价值交付</td>
                        <td>付费意愿、复购率</td>
                        <td>第51-75天</td>
                    </tr>
                    <tr>
                        <td><strong>放大</strong></td>
                        <td>规模扩张</td>
                        <td>裂变系数、LTV</td>
                        <td>第76-100天</td>
                    </tr>
                </tbody>
            </table>
            <p style="margin-top:15px;color:#666;font-size:13px;">
                💡 点击上方"获取100天计划"按钮，系统将生成个性化的入流追踪方案。
            </p>
        </div>';
    }

    /**
     * 注册admin菜单
     *
     * v16.0.3: REMOVED separate submenu registration.
     * Rendered as a section in tab-v18.php.
     */
    public static function register_admin_menu(): void {
        // No-op — rendered as a section in tab-v18.php
    }

    /**
     * 面板页面回调
     */
    public static function panel_page(): void {
        echo self::render_panel();
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
            'module_version' => '15.0.0-rc4',
            'title' => '入流追踪进度面板',
            'desc' => '100天进度可视化',
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Admin\Linked3_OS_Onboarding_Panel')) {
    add_action('init', ['\Linked3\Classes\OS\Admin\Linked3_OS_Onboarding_Panel', 'register'], 10);
}
