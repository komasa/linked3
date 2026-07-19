<?php
/**
 * Linked3 Linked3_OS_Capability_Stages_Ajax 14.8.0
 *
 * 能知三阶AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: Linked3_Neng_Zhi_Three_Stages
 *
 * @package Linked3\Classes\OS
 * @since 14.8.0
 * @version 14.8.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Capability Stages AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/class-linked3-neng-zhi-ajax.php
 * Original class: Linked3_Neng_Zhi_Ajax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class Linked3_OS_Capability_Stages_Ajax {

    /**
     * AJAX: 认知映射
     * Action: linked3_nengzhi_map
     */
    public static function ajax_map() : void {
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
     * AJAX: 自动检测
     * Action: linked3_nengzhi_detect
     */
    public static function ajax_detect() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_detect($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 获取三阶
     * Action: linked3_nengzhi_stages
     */
    public static function ajax_stages() : void {
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
     * v16.0.22修复: 原调用Neng_Zhi_Three_Stages::reverse_parse(不存在), 改为委托Linked3_Reverse_Engine
     */
    

    /**
     * 执行逆向转SEED
     * v16.0.22修复: 委托Linked3_Reverse_Engine
     */
    

    /**
     * 执行逆向对比
     * v16.0.22修复: 委托Linked3_Reverse_Engine
     */
    

    /**
     * 执行约束构建
     */
    private static function execute_constraint(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Neng_Zhi_Three_Stages')) {
            return ['error' => '目标类未加载'];
        }
        $content_type = $params['content_type'] ?? 'T1';
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Neng_Zhi_Three_Stages', 'derive_from_content_type')) {
            $constraint = call_user_func(['Linked3_Neng_Zhi_Three_Stages', 'derive_from_content_type'], $content_type);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Neng_Zhi_Three_Stages', 'assign_frequency')) {
            $module_id = $params['module_id'] ?? 'M001';
            $constraint = call_user_func(['Linked3_Neng_Zhi_Three_Stages', 'assign_frequency'], $module_id);
        } else {
            $constraint = call_user_func(['Linked3_Neng_Zhi_Three_Stages', 'get_all_options']);
        }
        return ['constraint' => $constraint];
    }

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
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Neng_Zhi_Three_Stages')) {
            return ['error' => '目标类未加载'];
        }
        // v18复审修复: 直接调用get_three_stages, 返回stages键 (前端期望data.stages)
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Neng_Zhi_Three_Stages', 'get_three_stages')) {
            $stages = call_user_func(['Linked3_Neng_Zhi_Three_Stages', 'get_three_stages']);
            return ['stages' => $stages, 'three_stages' => $stages];
        }
        return ['stages' => [], 'error' => 'get_three_stages方法不存在'];
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
    private static function execute_detect(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Neng_Zhi_Three_Stages')) {
            return ['error' => '目标类未加载'];
        }
        // v18复审修复: 前端传content, 后端应取content (原reader_type参数名错误)
        $content = $params['content'] ?? ($params['reader_type'] ?? '');
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Neng_Zhi_Three_Stages', 'auto_detect_stage')) {
            $detected = call_user_func(['Linked3_Neng_Zhi_Three_Stages', 'auto_detect_stage'], $content);
        } else {
            $detected = ['stage' => 'unknown'];
        }
        return $detected;
    }

    /**
     * 生成报告
     */
    


    /**
     * 注册所有AJAX端点
     */
    public static function register() : void {
        $actions = [
            'linked3_nengzhi_map' => 'ajax_map',
            'linked3_nengzhi_detect' => 'ajax_detect',
            'linked3_nengzhi_stages' => 'ajax_stages',
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
            'ajax_version' => '14.8.0',
            'target_class' => 'Linked3_Neng_Zhi_Three_Stages',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => '能知三阶AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_nengzhi_map' => '认知映射', 'linked3_nengzhi_detect' => '自动检测', 'linked3_nengzhi_stages' => '获取三阶'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\Linked3_OS_Capability_Stages_Ajax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\Linked3_OS_Capability_Stages_Ajax', 'register'], 5);
}
