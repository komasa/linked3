<?php

declare(strict_types=1);
/**
 * Linked3 OSEngineerRegistryAjax 14.5.0
 *
 * 31类工程师AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: OSEngineerRegistry
 *
 * @package Linked3\Reverse
 * @since 14.5.0
 * @version 14.5.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Engineer Registry AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/EngineerRegistryAjax.php
 * Original class: OSEngineerRegistryAjax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSEngineerRegistryAjax {

    /**
     * AJAX: 列出工程师
     * Action: linked3_engineer_list
     */
    public static function ajax_list() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_list();
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 注册工程师
     * Action: linked3_engineer_register
     */
    public static function ajax_register() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_register($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 获取分类
     * Action: linked3_engineer_categories
     */
    public static function ajax_categories() : void {
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
    

    /**
     * 获取选项/基线
     */
    private static function execute_get_options(): array {
        if (!class_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry')) {
            return ['error' => '目标类未加载'];
        }
        if (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_all_options')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_all_options']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_baseline')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_baseline']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_all_chart_types')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_all_chart_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_consciousness_layers')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_consciousness_layers']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_ru_liu_states')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_ru_liu_states']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_categories')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_categories']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_text_types')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_text_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_three_stages')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_three_stages']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_factors')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_factors']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_gate_thresholds')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_gate_thresholds']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_100day_plan')) {
            $options = call_user_func(['OSEngineerRegistry', 'get_100day_plan']);
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
    private static function execute_list(): array {
        if (!class_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry')) {
            return ['error' => '目标类未加载'];
        }
        if (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_all_engineers')) {
            $list = call_user_func(['OSEngineerRegistry', 'get_all_engineers']);
        } elseif (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'get_text_types')) {
            $list = call_user_func(['OSEngineerRegistry', 'get_text_types']);
        } else {
            $list = [];
        }
        return ['list' => $list];
    }

    /**
     * 注册
     */
    private static function execute_register(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry')) {
            return ['error' => '目标类未加载'];
        }
        $engineer_type = $params['engineer_type'] ?? '';
        if (method_exists('\Linked3\Classes\OS\Core\OSEngineerRegistry', 'register_engineer')) {
            $result = call_user_func(['OSEngineerRegistry', 'register_engineer'], $engineer_type, $params);
        } else {
            $result = false;
        }
        return ['registered' => $result];
    }

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
            'linked3_engineer_list' => 'ajax_list',
            'linked3_engineer_register' => 'ajax_register',
            'linked3_engineer_categories' => 'ajax_categories',
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
            'ajax_version' => '14.5.0',
            'target_class' => 'OSEngineerRegistry',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => '31类工程师AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_engineer_list' => '列出工程师', 'linked3_engineer_register' => '注册工程师', 'linked3_engineer_categories' => '获取分类'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\OSEngineerRegistryAjax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\OSEngineerRegistryAjax', 'register'], 5);
}
