<?php

declare(strict_types=1);
/**
 * Linked3 OSCli 15.0.0-rc6
 *
 * WP-CLI命令
 *
 * 命令: wp linked3 reverse / svg-stats 等
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc6
 * @version 15.0.0-rc6
 */

namespace Linked3\Classes\OS\Api;

/**
 * OS Module — OS CLI
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Api/V18Cli.php
 * Original class: OSCli
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSCli {


    /**
     * 注册WP-CLI命令
     */
    public static function register_commands(): void {
        if (!class_exists('WP_CLI')) {
            return;
        }
        
        WP_CLI::add_command('linked3 reverse', [__CLASS__, 'cmd_reverse']);
        WP_CLI::add_command('linked3 svg-stats', [__CLASS__, 'cmd_svg_stats']);
        WP_CLI::add_command('linked3 health', [__CLASS__, 'cmd_health']);
        WP_CLI::add_command('linked3 modules', [__CLASS__, 'cmd_modules']);
    }

    /**
     * 逆向解析命令
     */
    public static function cmd_reverse(array $args, array $assoc_args): void {
        $json = $assoc_args['json'] ?? '';
        $type = $assoc_args['type'] ?? 'visual_system';
        
        if (!class_exists('\Linked3\Classes\OS\Core\OSReverseEngine')) {
            WP_CLI::error('Reverse Engine not loaded');
            return;
        }
        
        $result = OSReverseEngine::reverse_parse($json, $type);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        } else {
            WP_CLI::success('逆向解析完成');
            WP_CLI::log(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    /**
     * SVG统计命令
     */
    public static function cmd_svg_stats(array $args, array $assoc_args): void {
        if (!class_exists('\Linked3\Classes\OS\Core\OSVisualAnalytics')) {
            WP_CLI::error('SVG Stats not loaded');
            return;
        }
        
        $chart_type = $assoc_args['type'] ?? null;
        if ($chart_type) {
            $result = OSVisualAnalytics::get_stats_by_chart_type($chart_type);
        } else {
            $result = OSVisualAnalytics::get_baseline();
        }
        WP_CLI::success('SVG统计获取完成');
        WP_CLI::log(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * 健康检查命令
     */
    public static function cmd_health(array $args, array $assoc_args): void {
        if (class_exists('\Linked3\Classes\OS\Api\OSIntegrationHub')) {
            $result = OSIntegrationHub::health_check();
            WP_CLI::success('健康检查完成');
            WP_CLI::log(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            WP_CLI::error('Integration Hub not loaded');
        }
    }

    /**
     * 模块列表命令
     */
    public static function cmd_modules(array $args, array $assoc_args): void {
        $modules = [
            ['v12.0.0', 'OSReverseEngine', '逆向8维度引擎'],
            ['v12.1.0', 'OSCapabilityLock', '能所结构'],
            ['v12.2.0', 'OSVisualAnalytics', 'SVG统计'],
            ['v12.3.0', 'OSConsciousnessLayer', '三层能观'],
            ['v12.4.0', 'OSOnboardingTracker', '入流追踪'],
            ['v12.5.0', 'OSEngineerRegistry', '31类工程师'],
            ['v12.6.0', 'OSTextCreation', '逆向文本'],
            ['v12.7.0', 'OSMomentumFlywheel', '洪流飞轮'],
            ['v12.8.0', 'OSCapabilityStages', '能知三阶'],
            ['v12.9.0', 'OSQualityGate', '质量门禁'],
        ];
        
        WP_CLI::log(sprintf('%-10s %-40s %-20s %s', '版本', '类名', '标题', '状态'));
        WP_CLI::log(str_repeat('-', 80));
        foreach ($modules as $mod) {
            $status = class_exists($mod[1]) ? '✓ 已加载' : '✗ 未加载';
            WP_CLI::log(sprintf('%-10s %-40s %-20s %s', $mod[0], $mod[1], $mod[2], $status));
        }
    }

    /**
     * 注册
     */
    public static function register(): void {
        add_action('cli_init', [__CLASS__, 'register_commands']);
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '15.0.0-rc6',
            'title' => 'WP-CLI命令',
            'commands' => ['linked3 reverse', 'linked3 svg-stats', 'linked3 health', 'linked3 modules'],
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Api\OSCli')) {
    add_action('init', ['\Linked3\Classes\OS\Api\OSCli', 'register'], 10);
}
