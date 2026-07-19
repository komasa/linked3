<?php

declare(strict_types=1);
/**
 * Linked3 OSVisualAnalyticsAjax 14.2.0
 *
 * SVG统计AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: Linked3_Svg_Meta_Stats
 *
 * @package Linked3\SvgStats
 * @since 14.2.0
 * @version 14.2.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Visual Analytics AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/SvgStatsAjax.php
 * Original class: Linked3_Svg_Stats_Ajax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSVisualAnalyticsAjax {

    /**
     * AJAX: 获取统计
     * Action: linked3_svg_stats
     * v18复审修复: 空壳→真实数据, 调用 Core 类返回1297个SVG的39维meta统计基线
     */
    public static function ajax_get_stats() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats')) {
                wp_send_json_error(['message' => __('SVG统计核心类未加载', 'linked3-ai')], 500);
            }

            // v18复审: 返回完整统计基线 (面向客户的功能化输出)
            $baseline = Linked3_Svg_Meta_Stats::SVG_STATS_BASELINE;
            $layout_dist = method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_layout_distribution')
                ? Linked3_Svg_Meta_Stats::get_layout_distribution()
                : [];
            $chart_types = method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_all_chart_types')
                ? Linked3_Svg_Meta_Stats::get_all_chart_types()
                : [];
            $scenes = method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_all_scenes')
                ? Linked3_Svg_Meta_Stats::get_all_scenes()
                : [];

            // 按图示类型获取统计 (若前端传了 chart_type)
            $chart_type = Request::string('chart_type', '');
            $type_stats = [];
            if (!empty($chart_type) && method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_stats_by_chart_type')) {
                $type_stats = Linked3_Svg_Meta_Stats::get_stats_by_chart_type($chart_type);
            }

            $result = [
                'summary' => [
                    'SVG总数' => $baseline['total_svgs'],
                    '场景数' => $baseline['total_scenes'],
                    'meta维度' => $baseline['total_meta_dims'],
                    '图示类型数' => count($chart_types),
                ],
                'averages' => [
                    '矩形数' => $baseline['avg_rect_count'],
                    '圆形数' => $baseline['avg_circle_count'] ?? 1.1,
                    '文本数' => $baseline['avg_text_count'],
                    '路径数' => $baseline['avg_path_count'],
                    '节点总数' => $baseline['avg_node_count'],
                    '颜色数' => $baseline['avg_color_count'],
                ],
                'maximums' => [
                    '最大矩形数' => $baseline['max_rect_count'],
                    '最大路径数' => $baseline['max_path_count'],
                    '最大文本数' => $baseline['max_text_count'],
                    '最大节点数' => $baseline['max_node_count'],
                ],
                'layout_distribution' => $layout_dist,
                'available_chart_types' => $chart_types,
                'available_scenes' => $scenes,
                'type_specific_stats' => $type_stats,
                'usage_hint' => '此基线来自1297个真实SVG实例的39维meta统计。设计新图示时，可参考平均值预估复杂度，参考最大值避免超载。',
            ];
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 预测原子数
     * Action: linked3_svg_predict
     */
    public static function ajax_predict() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_predict($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 获取基线
     * Action: linked3_svg_baseline
     */
    public static function ajax_baseline() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_get_options();
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }


    /**
     * 提取请求参数
     */
    private static function extract_params(): array {
        return [
            'engineer_type' => Request::string('engineer_type', ''),
            'target' => Request::textarea('target'),
            'json_raw' => Request::textarea('json_raw'),
            'result_a' => Request::textarea('result_a'),
            'result_b' => Request::textarea('result_b'),
            'content_type' => Request::string('content_type', 'T1'),
            'chart_type' => Request::string('chart_type', ''),
            'scene' => Request::string('scene', ''),
            'module_id' => Request::string('module_id', ''),
            'frequency' => Request::string('frequency', 'MF'),
            'day' => intval($_POST['day'] ?? 1),
            'state' => Request::string('state', ''),
            'text_type' => Request::string('text_type', ''),
            'target_text' => Request::textarea('target_text'),
            'cognitive_level' => Request::string('cognitive_level', ''),
            'reader_type' => Request::string('reader_type', ''),
            'reverse_result' => Request::textarea('reverse_result'),
            'prompt' => Request::textarea('prompt'),
            'flywheel_data' => Request::textarea('flywheel_data'),
        ];
    }

    /**
     * 执行逆向解析
     */
    

    /**
     * 执行逆向转SEED
     */
    

    /**
     * 执行逆向对比
     */
    

    /**
     * 执行约束构建
     */
    

    /**
     * 执行校验
     */
    

    /**
     * 执行注入
     */
    

    /**
     * 执行预测
     */
    private static function execute_predict(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats')) {
            return ['error' => '目标类未加载'];
        }
        $chart_type = $params['chart_type'] ?? 'D08';
        $prediction = call_user_func(['Linked3_Svg_Meta_Stats', 'predict_atom_count'], $chart_type);
        return ['prediction' => $prediction];
    }

    /**
     * 获取选项/基线
     */
    private static function execute_get_options(): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats')) {
            return ['error' => '目标类未加载'];
        }
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_all_options')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_all_options']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_baseline')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_baseline']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_all_chart_types')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_all_chart_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_consciousness_layers')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_consciousness_layers']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_ru_liu_states')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_ru_liu_states']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_categories')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_categories']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_text_types')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_text_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_three_stages')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_three_stages']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_factors')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_factors']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_gate_thresholds')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_gate_thresholds']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Svg_Meta_Stats', 'get_100day_plan')) {
            $options = call_user_func(['Linked3_Svg_Meta_Stats', 'get_100day_plan']);
        } else {
            $options = ['status' => 'no_options_method'];
        }
        return ['options' => $options];
    }

    /**
     * 获取状态
     */
    

    /**
     * 更新状态
     */
    

    /**
     * 列出
     */
    

    /**
     * 注册
     */
    

    /**
     * 改进建议
     */
    

    /**
     * 自动检测
     */
    

    /**
     * 生成报告
     */
    


    /**
     * 注册所有AJAX端点
     */
    public static function register() : void {
        $actions = [
            'linked3_svg_stats' => 'ajax_get_stats',
            'linked3_svg_predict' => 'ajax_predict',
            'linked3_svg_baseline' => 'ajax_baseline',
        ];

        foreach ($actions as $action => $method) {
            add_action('wp_ajax_' . $action, [__CLASS__, $method]);
            add_action('wp_ajax_nopriv_' . $action, [__CLASS__, $method]);
        }
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'ajax_version' => '14.2.0',
            'target_class' => 'Linked3_Svg_Meta_Stats',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => 'SVG统计AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_svg_stats' => '获取统计', 'linked3_svg_predict' => '预测原子数', 'linked3_svg_baseline' => '获取基线'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\OSVisualAnalyticsAjax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\OSVisualAnalyticsAjax', 'register'], 5);
}
