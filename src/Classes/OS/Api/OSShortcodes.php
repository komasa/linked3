<?php

declare(strict_types=1);
/**
 * Linked3 OSShortcodes 15.0.0-rc7
 *
 * 短代码支持
 *
 * 短代码: [linked3_reverse] / [linked3_svg_stats] 等
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc7
 * @version 15.0.0-rc7
 */

namespace Linked3\Classes\OS\Api;

/**
 * OS Module — OS Shortcodes
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Api/class-linked3-v18-shortcodes.php
 * Original class: V18_Shortcodes
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSShortcodes {


    /**
     * 注册短代码
     */
    public static function register_shortcodes(): void {
        add_shortcode('linked3_reverse', [__CLASS__, 'shortcode_reverse']);
        add_shortcode('linked3_svg_stats', [__CLASS__, 'shortcode_svg_stats']);
        add_shortcode('linked3_health', [__CLASS__, 'shortcode_health']);
        add_shortcode('linked3_modules', [__CLASS__, 'shortcode_modules']);
    }

    /**
     * 逆向拆解短代码
     */
    public static function shortcode_reverse(array $atts): string {
        $atts = shortcode_atts([
            'type' => 'visual_system',
            'target' => '',
        ], $atts);
        
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Reverse_Dimensions')) {
            return '<div class="linked3-error">逆向引擎未加载</div>';
        }
        
        $prompt = Linked3_Reverse_Dimensions::build_reverse_prompt($atts['type'], $atts['target']);
        return '<div class="linked3-reverse-shortcode"><pre>' . esc_html($prompt) . '</pre></div>';
    }

    /**
     * SVG统计短代码
     */
    public static function shortcode_svg_stats(array $atts): string {
        $atts = shortcode_atts([
            'chart_type' => '',
        ], $atts);
        
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Svg_Meta_Stats')) {
            return '<div class="linked3-error">SVG统计未加载</div>';
        }
        
        if (!empty($atts['chart_type'])) {
            $stats = Linked3_Svg_Meta_Stats::get_stats_by_chart_type($atts['chart_type']);
        } else {
            $stats = Linked3_Svg_Meta_Stats::get_baseline();
        }
        
        $html = '<div class="linked3-svg-stats-shortcode">';
        $html .= '<h3>SVG统计</h3>';
        $html .= '<table class="linked3-stats-table">';
        foreach ($stats as $key => $value) {
            if (!is_array($value)) {
                $html .= '<tr><td>' . esc_html($key) . '</td><td>' . esc_html($value) . '</td></tr>';
            }
        }
        $html .= '</table></div>';
        return $html;
    }

    /**
     * 健康检查短代码
     */
    public static function shortcode_health(array $atts): string {
        if (!class_exists('\Linked3\Classes\OS\Api\V18_Integration_Hub')) {
            return '<div class="linked3-error">集成中心未加载</div>';
        }
        
        $health = V18_Integration_Hub::health_check();
        $html = '<div class="linked3-health-shortcode">';
        $html .= '<h3>V18集成健康</h3>';
        $html .= '<pre>' . esc_html(json_encode($health, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
        $html .= '</div>';
        return $html;
    }

    /**
     * 模块列表短代码
     */
    public static function shortcode_modules(array $atts): string {
        $modules = [
            ['v12.0.0', 'Linked3_Reverse_Engine'],
            ['v12.1.0', 'Linked3_Neng_Suo_Structure'],
            ['v12.2.0', 'Linked3_Svg_Meta_Stats'],
            ['v12.3.0', 'Linked3_Three_Layer_Consciousness'],
            ['v12.4.0', 'Linked3_Ru_Liu_Tracker'],
            ['v12.5.0', 'Linked3_Reverse_Engineer_Registry'],
            ['v12.6.0', 'Linked3_Reverse_Text_Creation'],
            ['v12.7.0', 'Linked3_Hong_Liu_Flywheel'],
            ['v12.8.0', 'Linked3_Neng_Zhi_Three_Stages'],
            ['v12.9.0', 'Linked3_Reverse_Quality_Gate'],
        ];
        
        $html = '<div class="linked3-modules-shortcode"><ul>';
        foreach ($modules as $mod) {
            $status = class_exists($mod[1]) ? '✓' : '✗';
            $html .= '<li>' . $status . ' ' . esc_html($mod[0]) . ' ' . esc_html($mod[1]) . '</li>';
        }
        $html .= '</ul></div>';
        return $html;
    }

    /**
     * 注册
     */
    public static function register(): void {
        add_action('init', [__CLASS__, 'register_shortcodes']);
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '15.0.0-rc7',
            'title' => '短代码支持',
            'shortcodes' => ['linked3_reverse', 'linked3_svg_stats', 'linked3_health', 'linked3_modules'],
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Api\OSShortcodes')) {
    add_action('init', ['\Linked3\Classes\OS\Api\OSShortcodes', 'register'], 10);
}
