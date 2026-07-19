<?php

declare(strict_types=1);
/**
 * Linked3 OSOnboardingAjax 14.4.0
 *
 * 入流追踪AJAX接口
 *
 * 来源: V18方法论反哺 v14.x系列 AJAX接口层
 * 目标类: Linked3_Ru_Liu_Tracker
 *
 * @package Linked3\Classes\OS
 * @since 14.4.0
 * @version 14.4.0
 */

namespace Linked3\Classes\OS\Ajax;

/**
 * OS Module — Onboarding AJAX
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Ajax/RuLiuAjax.php
 * Original class: Linked3_Ru_Liu_Ajax
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSOnboardingAjax {

    /**
     * AJAX: 获取状态
     * Action: linked3_ruliu_status
     */
    public static function ajax_status() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_get_status($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 更新状态
     * Action: linked3_ruliu_update
     */
    public static function ajax_update() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            $params = self::extract_params();
            $result = self::execute_update($params);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }
    }

    /**
     * AJAX: 获取计划
     * Action: linked3_ruliu_plan
     * v18复审修复E1/S1: 接收用户输入(职业/赛道/目标)生成定制化计划
     */
    public static function ajax_plan() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足', 'linked3-ai')], 403);
        }

        try {
            // v18复审: 接收用户输入参数
            $user_input = [
                'profession'  => Request::string('profession', ''),
                'track'       => Request::string('track', ''),
                'goal'        => Request::string('goal', ''),
                'platform'    => Request::string('platform', '公众号'),
                'current_day' => intval($_POST['current_day'] ?? 1),
            ];

            // 若有用户输入, 生成定制计划; 否则返回基础计划(兼容旧调用)
            if (!empty($user_input['profession']) || !empty($user_input['track']) || !empty($user_input['goal'])) {
                if (class_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker') && method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'generate_personalized_plan')) {
                    $result = Linked3_Ru_Liu_Tracker::generate_personalized_plan($user_input);
                    $result['plan_type'] = 'personalized';
                } else {
                    $result = self::execute_get_options();
                    $result['plan_type'] = 'fallback';
                }
            } else {
                $result = self::execute_get_options();
                $result['plan_type'] = 'basic';
            }

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
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker')) {
            return ['error' => '目标类未加载'];
        }
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_all_options')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_all_options']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_baseline')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_baseline']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_all_chart_types')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_all_chart_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_consciousness_layers')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_consciousness_layers']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_ru_liu_states')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_ru_liu_states']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_categories')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_categories']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_text_types')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_text_types']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_three_stages')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_three_stages']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_factors')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_factors']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_gate_thresholds')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_gate_thresholds']);
        } elseif (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'get_100day_plan')) {
            $options = call_user_func(['Linked3_Ru_Liu_Tracker', 'get_100day_plan']);
        } else {
            $options = ['status' => 'no_options_method'];
        }
        return ['options' => $options];
    }

    /**
     * 获取状态
     */
    private static function execute_get_status(array $params): array {
        if (!class_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker')) {
            return ['error' => '目标类未加载'];
        }
        $day = $params['day'] ?? 1;
        if (method_exists('\Linked3\Classes\OS\Ajax\Linked3_Ru_Liu_Tracker', 'calculate_state_progress')) {
            $status = call_user_func(['Linked3_Ru_Liu_Tracker', 'calculate_state_progress'], $day);
        } else {
            $status = ['day' => $day];
        }
        return ['status' => $status];
    }

    /**
     * 更新状态
     */
    private static function execute_update(array $params): array {
        $day = $params['day'] ?? 1;
        $state = $params['state'] ?? '';
        update_option('linked3_ruliu_day_' . $day, $state);
        return ['updated' => true, 'day' => $day, 'state' => $state];
    }

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
            'linked3_ruliu_status' => 'ajax_status',
            'linked3_ruliu_update' => 'ajax_update',
            'linked3_ruliu_plan' => 'ajax_plan',
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
            'ajax_version' => '14.4.0',
            'target_class' => 'Linked3_Ru_Liu_Tracker',
            'endpoints_count' => count(self::get_endpoints()),
            'title' => '入流追踪AJAX接口',
        ];
    }

    /**
     * 获取端点列表
     */
    public static function get_endpoints(): array {
        return ['linked3_ruliu_status' => '获取状态', 'linked3_ruliu_update' => '更新状态', 'linked3_ruliu_plan' => '获取计划'];
    }

}

// 注册AJAX
if (class_exists('\Linked3\Classes\OS\Ajax\OSOnboardingAjax')) {
    add_action('init', ['\Linked3\Classes\OS\Ajax\OSOnboardingAjax', 'register'], 5);
}
