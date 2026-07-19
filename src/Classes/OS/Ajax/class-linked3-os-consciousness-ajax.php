<?php
/**
 * Linked3 Linked3_OS_Consciousness_Ajax 14.3.0
 *
 * 三层能观AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: Linked3_Three_Layer_Consciousness
 *
 * @package Linked3\Classes\OS
 * @since 14.3.0
 * @version 14.3.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Consciousness AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/class-linked3-consciousness-ajax.php
 * Original class: Linked3_Consciousness_Ajax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class Linked3_OS_Consciousness_Ajax {

    /**
     * AJAX: 分配频率
     * Action: linked3_frequency_assign
     */
    public static function ajax_assign() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_constraint($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 校验分布
     * Action: linked3_frequency_validate
     */
    public static function ajax_validate() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_validate($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 获取选项
     * Action: linked3_frequency_options
     */
    public static function ajax_options() : void {
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
     * v16.0.22修复: 原调用reverse_parse(不存在), 改为委托给Linked3_Reverse_Engine
     */
    

    /**
     * 执行逆向转SEED
     * v16.0.22修复: 原调用reverse_to_seed(不存在), 改为委托给Linked3_Reverse_Engine
     */
    

    /**
     * 执行逆向对比
     * v16.0.22修复: 原调用reverse_compare(不存在), 改为委托给Linked3_Reverse_Engine
     */
    

    /**
     * 执行约束构建
     */
    private static function execute_constraint(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness')) {
            return ['error' => '目标类未加载'];
        }
        // v18复审修复: 前端传module_type+content, 直接调用assign_frequency(module_type, content)
        $module_type = $params['module_type'] ?? ($params['content_type'] ?? 'method');
        $content = $params['content'] ?? '';
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'assign_frequency')) {
            $constraint = call_user_func(['Linked3_Three_Layer_Consciousness', 'assign_frequency'], $module_type, $content);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'derive_from_content_type')) {
            $constraint = call_user_func(['Linked3_Three_Layer_Consciousness', 'derive_from_content_type'], $module_type);
        } else {
            $constraint = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_all_options']);
        }
        return $constraint;
    }

    /**
     * 执行校验
     */
    private static function execute_validate(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness')) {
            return ['error' => '目标类未加载'];
        }
        $json_raw = $params['json_raw'] ?? '';
        $parsed = json_decode($json_raw, true) ?: [];
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'validate_neng_suo')) {
            $result = call_user_func(['Linked3_Three_Layer_Consciousness', 'validate_neng_suo'], $parsed, $params);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'validate_frequency_distribution')) {
            $result = call_user_func(['Linked3_Three_Layer_Consciousness', 'validate_frequency_distribution'], $parsed);
        } else {
            $result = ['valid' => true];
        }
        return ['validation' => $result];
    }

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
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness')) {
            return ['error' => '目标类未加载'];
        }
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_all_options')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_all_options']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_baseline')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_baseline']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_all_chart_types')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_all_chart_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_consciousness_layers')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_consciousness_layers']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_ru_liu_states')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_ru_liu_states']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_categories')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_categories']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_text_types')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_text_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_three_stages')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_three_stages']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_factors')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_factors']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_gate_thresholds')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_gate_thresholds']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Three_Layer_Consciousness', 'get_100day_plan')) {
            $options = call_user_func(['Linked3_Three_Layer_Consciousness', 'get_100day_plan']);
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
            'linked3_frequency_assign' => 'ajax_assign',
            'linked3_frequency_validate' => 'ajax_validate',
            'linked3_frequency_options' => 'ajax_options',
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
            'ajax_version' => '14.3.0',
            'target_class' => 'Linked3_Three_Layer_Consciousness',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => '三层能观AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_frequency_assign' => '分配频率', 'linked3_frequency_validate' => '校验分布', 'linked3_frequency_options' => '获取选项'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\Linked3_OS_Consciousness_Ajax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\Linked3_OS_Consciousness_Ajax', 'register'], 5);
}
