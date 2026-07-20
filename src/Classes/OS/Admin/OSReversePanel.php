<?php

declare(strict_types=1);
/**
 * Linked3 OSReversePanel 15.0.0-rc2
 *
 * 逆向拆解操作面板
 *
 * 逆向拆解操作界面+JSON结果展示
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc2
 * @version 15.0.0-rc2
 */

namespace Linked3\Classes\OS\Admin;

/**
 * OS Module — Reverse Panel
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Admin/V18ReversePanel.php
 * Original class: OSReversePanel
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSReversePanel {


    /**
     * 渲染面板
     */
    public static function render_panel(): string {
        $html = '<div class="wrap linked3-v18-panel">';
        $html .= '<h1>逆向拆解操作面板 <span class="version">v15.0.0</span></h1>';
        $html .= '<p>逆向拆解操作界面+JSON结果展示</p>';
        $html .= '<div class="panel-content">' . self::render_content() . '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 渲染内容
     * v16.0.12: Replaced placeholder with actual reverse engineering guide
     */
    private static function render_content(): string {
        return '<div class="v18-reverse-guide">
            <h4>逆向拆解使用指南</h4>
            <ol>
                <li><strong>准备素材</strong>: 从AI生成结果中复制JSON或作品描述文本</li>
                <li><strong>选择工程师类型</strong>: 根据素材类型选择对应的逆向工程师</li>
                <li><strong>执行拆解</strong>: 点击"开始逆向拆解"按钮，系统将进行8维度分析</li>
                <li><strong>查看结果</strong>: 拆解结果包含结构、风格、配色、构图等维度</li>
                <li><strong>转SEED</strong>: 可将拆解结果转化为可复用的SEED DNA</li>
            </ol>
            <h4>支持的工程师类型</h4>
            <ul>
                <li><strong>视觉系统逆向工程师</strong>: 拆解图片/设计的视觉元素</li>
                <li><strong>音视频系统逆向工程师</strong>: 拆解视频/音频的节奏与结构</li>
                <li><strong>品牌六要素逆向工程师</strong>: 拆解品牌定位、视觉、声音等</li>
                <li><strong>工程系统逆向工程师</strong>: 拆解技术架构与实现</li>
                <li><strong>方法论系统逆向工程师</strong>: 拆解思维框架与流程</li>
                <li><strong>Motion Prompt逆向工程师</strong>: 拆解动态提示词结构</li>
            </ul>
        </div>';
    }

    /**
     * 注册admin菜单
     *
     * v16.0.3: REMOVED separate submenu registration.
     * This panel is now rendered as a section within the main dashboard's
     * V18 tab (?page=linked3-dashboard&tab=v18).
     * The render_panel() method is called directly by tab-v18.php.
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
            'module_version' => '15.0.0-rc2',
            'title' => '逆向拆解操作面板',
            'desc' => '逆向拆解操作界面+JSON结果展示',
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Admin\OSReversePanel')) {
    add_action('init', ['\Linked3\Classes\OS\Admin\OSReversePanel', 'register'], 10);
}
