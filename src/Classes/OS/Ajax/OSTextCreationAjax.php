<?php

declare(strict_types=1);
/**
 * Linked3 OSTextCreationAjax 14.6.0
 *
 * 逆向文本AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: Linked3_Reverse_Text_Creation
 *
 * @package Linked3\Reverse
 * @since 14.6.0
 * @version 14.6.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Text Creation AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/ReverseTextAjax.php
 * Original class: Linked3_Reverse_Text_Ajax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSTextCreationAjax {

    /**
     * AJAX: 文本逆向
     * Action: linked3_text_reverse
     */
    public static function ajax_reverse() : void {
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
     * AJAX: 获取类型
     * Action: linked3_text_types
     */
    public static function ajax_types() : void {
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
     * AJAX: 获取维度
     * Action: linked3_text_dimensions
     */
    public static function ajax_dimensions() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = ['status' => 'ok'];
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
    private static function execute_reverse(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation')) {
            return ['error' => '目标类未加载'];
        }
        $json_raw = $params['json_raw'] ?? '';
        if (empty($json_raw)) {
            return ['error' => 'json_raw参数为空'];
        }
        $engineer_type = $params['engineer_type'] ?? 'visual_system';
        $result = call_user_func(['Linked3_Reverse_Text_Creation', 'reverse_parse'], $json_raw, $engineer_type);
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
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation')) {
            return ['error' => '目标类未加载'];
        }
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_all_options')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_all_options']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_baseline')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_baseline']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_all_chart_types')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_all_chart_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_consciousness_layers')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_consciousness_layers']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_ru_liu_states')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_ru_liu_states']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_categories')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_categories']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_text_types')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_text_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_three_stages')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_three_stages']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_factors')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_factors']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_gate_thresholds')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_gate_thresholds']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Text_Creation', 'get_100day_plan')) {
            $options = call_user_func(['Linked3_Reverse_Text_Creation', 'get_100day_plan']);
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
            'linked3_text_reverse' => 'ajax_reverse',
            'linked3_text_types' => 'ajax_types',
            'linked3_text_dimensions' => 'ajax_dimensions',
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
            'ajax_version' => '14.6.0',
            'target_class' => 'Linked3_Reverse_Text_Creation',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => '逆向文本AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_text_reverse' => '文本逆向', 'linked3_text_types' => '获取类型', 'linked3_text_dimensions' => '获取维度'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\OSTextCreationAjax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\OSTextCreationAjax', 'register'], 5);
}
