<?php
/**
 * Linked3 Linked3_OS_Momentum_Ajax 14.7.0
 *
 * 洪流飞轮AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: Linked3_Hong_Liu_Flywheel
 *
 * @package Linked3\Classes\OS
 * @since 14.7.0
 * @version 14.7.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Momentum AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/class-linked3-hong-liu-ajax.php
 * Original class: Linked3_Hong_Liu_Ajax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class Linked3_OS_Momentum_Ajax {

    /**
     * AJAX: 计算飞轮分数
     * Action: linked3_flywheel_score
     */
    public static function ajax_score() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_reverse($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 改进建议
     * Action: linked3_flywheel_suggest
     */
    public static function ajax_suggest() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_suggest($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 获取因子
     * Action: linked3_flywheel_factors
     */
    public static function ajax_factors() : void {
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
            'engineer_type' => Linked3_Request::string('engineer_type', ''),
            'target' => Linked3_Request::textarea('target'),
            'json_raw' => Linked3_Request::textarea('json_raw'),
            'result_a' => Linked3_Request::textarea('result_a'),
            'result_b' => Linked3_Request::textarea('result_b'),
            'content_type' => Linked3_Request::string('content_type', 'T1'),
            'chart_type' => Linked3_Request::string('chart_type', ''),
            'scene' => Linked3_Request::string('scene', ''),
            'module_id' => Linked3_Request::string('module_id', ''),
            'frequency' => Linked3_Request::string('frequency', 'MF'),
            'day' => intval($_POST['day'] ?? 1),
            'state' => Linked3_Request::string('state', ''),
            'text_type' => Linked3_Request::string('text_type', ''),
            'target_text' => Linked3_Request::textarea('target_text'),
            'cognitive_level' => Linked3_Request::string('cognitive_level', ''),
            'reader_type' => Linked3_Request::string('reader_type', ''),
            'reverse_result' => Linked3_Request::textarea('reverse_result'),
            'prompt' => Linked3_Request::textarea('prompt'),
            'flywheel_data' => Linked3_Request::textarea('flywheel_data'),
        ];
    }

    /**
     * 执行逆向解析
     */
    private static function execute_reverse(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel')) {
            return ['error' => '目标类未加载'];
        }
        $json_raw = $params['json_raw'] ?? '';
        if (empty($json_raw)) {
            return ['error' => 'json_raw参数为空'];
        }
        $engineer_type = $params['engineer_type'] ?? 'visual_system';
        $result = call_user_func(['Linked3_Hong_Liu_Flywheel', 'reverse_parse'], $json_raw, $engineer_type);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }
        return ['result' => $result];
    }

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
    

    /**
     * 获取选项/基线
     */
    private static function execute_get_options(): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel')) {
            return ['error' => '目标类未加载'];
        }
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_all_options')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_all_options']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_baseline')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_baseline']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_all_chart_types')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_all_chart_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_consciousness_layers')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_consciousness_layers']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_ru_liu_states')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_ru_liu_states']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_categories')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_categories']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_text_types')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_text_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_three_stages')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_three_stages']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_factors')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_factors']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_gate_thresholds')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_gate_thresholds']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'get_100day_plan')) {
            $options = call_user_func(['Linked3_Hong_Liu_Flywheel', 'get_100day_plan']);
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
    private static function execute_suggest(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel')) {
            return ['error' => '目标类未加载'];
        }
        $flywheel_data = json_decode($params['flywheel_data'] ?? '{}', true) ?: [];
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Hong_Liu_Flywheel', 'suggest_improvement')) {
            $suggestion = call_user_func(['Linked3_Hong_Liu_Flywheel', 'suggest_improvement'], $flywheel_data);
        } else {
            $suggestion = ['suggestion' => '暂无建议'];
        }
        return ['suggestion' => $suggestion];
    }

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
            'linked3_flywheel_score' => 'ajax_score',
            'linked3_flywheel_suggest' => 'ajax_suggest',
            'linked3_flywheel_factors' => 'ajax_factors',
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
            'ajax_version' => '14.7.0',
            'target_class' => 'Linked3_Hong_Liu_Flywheel',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => '洪流飞轮AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_flywheel_score' => '计算飞轮分数', 'linked3_flywheel_suggest' => '改进建议', 'linked3_flywheel_factors' => '获取因子'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\Linked3_OS_Momentum_Ajax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\Linked3_OS_Momentum_Ajax', 'register'], 5);
}
