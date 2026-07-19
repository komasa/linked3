<?php
/**
 * Linked3 Linked3_OS_Reverse_Ajax 14.0.0
 *
 * 逆向引擎AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: Linked3_Reverse_Engine
 *
 * @package Linked3\Reverse
 * @since 14.0.0
 * @version 14.0.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Reverse AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/class-linked3-reverse-ajax.php
 * Original class: Linked3_Reverse_Ajax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class Linked3_OS_Reverse_Ajax {

    /**
     * AJAX: 逆向解析
     * Action: linked3_reverse_parse
     */
    public static function ajax_parse() : void {
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
     * AJAX: 逆向转SEED
     * Action: linked3_reverse_to_seed
     */
    public static function ajax_to_seed() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_to_seed($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 逆向对比
     * Action: linked3_reverse_compare
     */
    public static function ajax_compare() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_compare($params);
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
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Engine')) {
            return ['error' => '目标类未加载'];
        }
        $json_raw = $params['json_raw'] ?? '';
        if (empty($json_raw)) {
            return ['error' => 'json_raw参数为空'];
        }
        $engineer_type = $params['engineer_type'] ?? 'visual_system';
        $result = call_user_func(['Linked3_Reverse_Engine', 'reverse_parse'], $json_raw, $engineer_type);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }
        return ['result' => $result];
    }

    /**
     * 执行逆向转SEED
     */
    private static function execute_to_seed(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Engine')) {
            return ['error' => '目标类未加载'];
        }
        $json_raw = $params['json_raw'] ?? '';
        if (empty($json_raw)) {
            return ['error' => 'json_raw参数为空'];
        }
        $parsed = call_user_func(['Linked3_Reverse_Engine', 'reverse_parse'], $json_raw);
        if (is_wp_error($parsed)) {
            return ['error' => $parsed->get_error_message()];
        }
        $seed = call_user_func(['Linked3_Reverse_Engine', 'reverse_to_seed'], $parsed);
        return ['seed' => $seed];
    }

    /**
     * 执行逆向对比
     */
    private static function execute_compare(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Reverse_Engine')) {
            return ['error' => '目标类未加载'];
        }
        $a = $params['result_a'] ?? '';
        $b = $params['result_b'] ?? '';
        $parsed_a = json_decode($a, true) ?: [];
        $parsed_b = json_decode($b, true) ?: [];
        $comparison = call_user_func(['Linked3_Reverse_Engine', 'reverse_compare'], $parsed_a, $parsed_b);
        return ['comparison' => $comparison];
    }

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
            'linked3_reverse_parse' => 'ajax_parse',
            'linked3_reverse_to_seed' => 'ajax_to_seed',
            'linked3_reverse_compare' => 'ajax_compare',
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
            'ajax_version' => '14.0.0',
            'target_class' => 'Linked3_Reverse_Engine',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => '逆向引擎AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_reverse_parse' => '逆向解析', 'linked3_reverse_to_seed' => '逆向转SEED', 'linked3_reverse_compare' => '逆向对比'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\Linked3_OS_Reverse_Ajax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\Linked3_OS_Reverse_Ajax', 'register'], 5);
}
