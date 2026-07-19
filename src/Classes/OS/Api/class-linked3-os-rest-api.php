<?php
/**
 * Linked3 Linked3_OS_Rest_Api 15.0.0-rc5
 *
 * REST API端点注册
 *
 * REST端点: /linked3/v1/reverse /svg-stats 等
 *
 * @package Linked3\Integration
 * @since 15.0.0-rc5
 * @version 15.0.0-rc5
 */

namespace Linked3\Classes\OS\Api;

/**
 * OS Module — OS REST API
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Api/class-linked3-v18-rest-api.php
 * Original class: Linked3_V18_Rest_Api
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class Linked3_OS_Rest_Api {


    /**
     * REST API命名空间
     */
    const REST_NAMESPACE = 'linked3/v1';

    /**
     * 注册REST路由
     */
    public static function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/reverse/parse', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_reverse_parse'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
        
        register_rest_route(self::REST_NAMESPACE, '/svg-stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_svg_stats'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
        
        register_rest_route(self::REST_NAMESPACE, '/neng-suo/constraint', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_neng_constraint'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
        
        register_rest_route(self::REST_NAMESPACE, '/flywheel/score', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_flywheel_score'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
        
        register_rest_route(self::REST_NAMESPACE, '/quality/check', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_quality_check'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
        
        register_rest_route(self::REST_NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_health'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * 权限检查
     */
    public static function check_permission(\WP_REST_Request $request): bool {
        return current_user_can('edit_posts');
    }

    /**
     * 逆向解析
     */
    public static function rest_reverse_parse(\WP_REST_Request $request): \WP_REST_Response {
        $json = $request->get_param('json') ?? '';
        $type = $request->get_param('type') ?? 'visual_system';
        
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Reverse_Engine')) {
            return new \WP_REST_Response(['error' => 'Reverse Engine not loaded'], 500);
        }
        
        $result = Linked3_Reverse_Engine::reverse_parse($json, $type);
        return new \WP_REST_Response($result, 200);
    }

    /**
     * SVG统计
     */
    public static function rest_svg_stats(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Svg_Meta_Stats')) {
            return new \WP_REST_Response(['error' => 'SVG Stats not loaded'], 500);
        }
        
        $chart_type = $request->get_param('chart_type');
        if ($chart_type) {
            $result = Linked3_Svg_Meta_Stats::get_stats_by_chart_type($chart_type);
        } else {
            $result = Linked3_Svg_Meta_Stats::get_baseline();
        }
        return new \WP_REST_Response($result, 200);
    }

    /**
     * 能知约束
     */
    public static function rest_neng_constraint(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Neng_Suo_Structure')) {
            return new \WP_REST_Response(['error' => 'Neng-Suo not loaded'], 500);
        }
        
        $content_type = $request->get_param('content_type') ?? 'T1';
        $result = Linked3_Neng_Suo_Structure::derive_from_content_type($content_type);
        return new \WP_REST_Response($result, 200);
    }

    /**
     * 飞轮分数
     */
    public static function rest_flywheel_score(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Hong_Liu_Flywheel')) {
            return new \WP_REST_Response(['error' => 'Flywheel not loaded'], 500);
        }
        
        $input = $request->get_json_params() ?? [];
        $result = Linked3_Hong_Liu_Flywheel::calculate_flywheel_score($input);
        return new \WP_REST_Response($result, 200);
    }

    /**
     * 质量检查
     */
    public static function rest_quality_check(\WP_REST_Request $request): \WP_REST_Response {
        if (!class_exists('\Linked3\Classes\OS\Api\Linked3_Reverse_Quality_Gate')) {
            return new \WP_REST_Response(['error' => 'Quality Gate not loaded'], 500);
        }
        
        $input = $request->get_json_params() ?? [];
        $result = Linked3_Reverse_Quality_Gate::generate_quality_report($input);
        return new \WP_REST_Response($result, 200);
    }

    /**
     * 健康检查
     */
    public static function rest_health(\WP_REST_Request $request): \WP_REST_Response {
        if (class_exists('\Linked3\Classes\OS\Api\Linked3_V18_Integration_Hub')) {
            $result = Linked3_V18_Integration_Hub::health_check();
        } else {
            $result = ['error' => 'Integration Hub not loaded'];
        }
        return new \WP_REST_Response($result, 200);
    }

    /**
     * 注册
     */
    public static function register(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '15.0.0-rc5',
            'title' => 'REST API端点注册',
            'namespace' => self::REST_NAMESPACE,
            'endpoints' => ['/reverse/parse', '/svg-stats', '/neng-suo/constraint', '/flywheel/score', '/quality/check', '/health'],
        ];
    }

}

// 注册模块
if (class_exists('\Linked3\Classes\OS\Api\Linked3_OS_Rest_Api')) {
    add_action('init', ['\Linked3\Classes\OS\Api\Linked3_OS_Rest_Api', 'register'], 10);
}
