<?php
/**
 * 小红书 AJAX 处理器 — v19.2.
 *
 * 处理小红书脚本生成、配图提示词优化等 AJAX 请求。
 * 吸收小红书生成器的异步生成模式，适配 WordPress AJAX 架构。
 *
 * @package Linked3
 * @subpackage Classes\XHS
 */

namespace Linked3\Classes\XHS;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_XHS_Ajax_Actions
{
    /**
     * 注册 AJAX 钩子。
     */
    public static function register()
    : void {
        add_action('wp_ajax_linked3_xhs_generate', [__CLASS__, 'ajax_generate']);
        add_action('wp_ajax_linked3_xhs_optimize_prompt', [__CLASS__, 'ajax_optimize_prompt']);
    }

    /**
     * AJAX: 生成小红书脚本。
     */
    public static function ajax_generate()
    : void {
        // v19.3.0: 使用统一 AJAX 防御层（fatal→JSON + nonce + capability）
        \Linked3\Classes\Core\Linked3_AJAX_Guard::protect('linked3_xhs', 'edit_posts');

        $params = [
            'topic'        => sanitize_text_field($_POST['topic'] ?? ''),
            'keyword'      => sanitize_text_field($_POST['keyword'] ?? ''),
            'style'        => sanitize_text_field($_POST['style'] ?? 'lifestyle'),
            'custom_style' => sanitize_textarea_field($_POST['custom_style'] ?? ''),
            'page_count'   => (int) ($_POST['page_count'] ?? 5),
            'model'        => sanitize_text_field($_POST['model'] ?? ''),
            'v15_context'  => [],
        ];

        // V15 上下文（如果存在）
        $v15_raw = $_POST['v15_context'] ?? [];
        if (is_array($v15_raw)) {
            foreach ($v15_raw as $k => $v) {
                $params['v15_context'][sanitize_key($k)] = sanitize_text_field($v);
            }
        }

        $generator = new Linked3_XHS_Generator();
        $result = $generator->generate_script($params);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ], $result->get_error_data()['status'] ?? 500);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: 优化配图提示词。
     */
    public static function ajax_optimize_prompt()
    : void {
        // v19.3.0: 使用统一 AJAX 防御层
        \Linked3\Classes\Core\Linked3_AJAX_Guard::protect('linked3_xhs', 'edit_posts');

        $raw_prompt = sanitize_textarea_field($_POST['image_prompt'] ?? '');
        $page_title = sanitize_text_field($_POST['page_title'] ?? '');
        $is_cover   = !empty($_POST['is_cover']);
        $style      = sanitize_text_field($_POST['style'] ?? 'lifestyle');

        if (empty($raw_prompt)) {
            wp_send_json_error(['message' => __('需要配图提示词', 'linked3')], 400);
        }

        // 构建优化提示词
        $optimize_prompt = "请优化以下小红书配图提示词，使其更加详细和具体。\n\n";
        $optimize_prompt .= "原始提示词: {$raw_prompt}\n";
        $optimize_prompt .= "页面标题: {$page_title}\n";
        $optimize_prompt .= "是否封面: " . ($is_cover ? '是（需要最具视觉冲击力）' : '否') . "\n";
        $optimize_prompt .= "风格: {$style}\n\n";
        $optimize_prompt .= "要求：\n";
        $optimize_prompt .= "1. 用英文输出\n";
        $optimize_prompt .= "2. 包含：主体 + 场景 + 光线 + 色调 + 构图 + 风格\n";
        $optimize_prompt .= "3. 比例：3:4 竖版\n";
        $optimize_prompt .= "4. 只输出优化后的提示词，不要其他文字";

        $model = get_option(LINKED3_OPTION_PREFIX . 'default_chat_model', 'gpt-4o-mini');
        // v19.2.1 修复：chat() 三参签名 + 单例 + try/catch（同 generate 路径）
        $dispatcher = \Linked3\Classes\Core\Linked3_AI_Dispatcher::instance();
        $messages = [
            ['role' => 'system', 'content' => '你是专业的AI绘画提示词工程师。'],
            ['role' => 'user',   'content' => $optimize_prompt],
        ];
        $options = [
            'model'       => $model,
            'temperature' => 0.6,
            'max_tokens'  => 500,
            'module'      => 'xhs_optimize',
            'user_id'     => get_current_user_id(),
        ];
        $config = ['fallback_providers' => ['deepseek', 'zhipu']];

        try {
            $result = $dispatcher->chat($messages, $options, $config);
        } catch (\RuntimeException $e) {
            wp_send_json_error(['message' => $e->getMessage()], 502);
        }

        wp_send_json_success([
            'optimized_prompt' => trim($result['content'] ?? ''),
        ]);
    }
}
