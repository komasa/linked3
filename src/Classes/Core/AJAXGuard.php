<?php

declare(strict_types=1);
/**
 * AJAX Guard.
 *
 * Unified AJAX defense layer with fatal-error-to-JSON conversion,
 * nonce verification, and capability checking.
 *
 * @package Linked3
 * @subpackage Classes\Core
 */

namespace Linked3\Classes\Core;

if (!defined('ABSPATH')) {
    exit;
}

class AJAXGuard
{
    /**
     * 已注册的 shutdown handler（防止重复注册）.
     */
    private static $registered = false;

    /**
     * 一键防御：fatal error → JSON + nonce + capability.
     *
     * @param string $nonce_action nonce action 名称
     * @param string $capability   所需 capability（默认 edit_posts）
     * @param string $nonce_field  POST 中 nonce 字段名（默认 'nonce'）
     * @return void  校验失败时直接 wp_send_json_error 并 exit
     */
    public static function protect($nonce_action = '', $capability = 'edit_posts', $nonce_field = 'nonce')
    {
        // 1. 注册 fatal error → JSON 转换（仅注册一次）
        if (!self::$registered) {
            self::register_fatal_handler();
            self::$registered = true;
        }

        // 2. Capability 校验
        if (!current_user_can($capability)) {
            self::json_error(__('权限不足。', 'linked3'), 403);
        }

        // 3. Nonce 校验
        if (!empty($nonce_action)) {
            $nonce = isset($_POST[$nonce_field]) ? sanitize_text_field(wp_unslash($_POST[$nonce_field])) : '';
            if (!wp_verify_nonce($nonce, $nonce_action)) {
                self::json_error(__('安全校验失败。', 'linked3'), 403);
            }
        }
    }

    /**
     * 注册 fatal error → JSON 转换的 shutdown handler.
     */
    private static function register_fatal_handler()
    {
        register_shutdown_function(function () {
            $err = error_get_last();
            if (!$err) {
                return;
            }
            $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($err['type'], $fatal_types, true)) {
                return;
            }

            // 清除任何缓冲输出（PHP warnings/notices 可能已写入）
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // 确保 JSON Content-Type
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }

            $message = '服务器内部错误: ' . $err['message'];
            $detail  = [];
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $detail['file'] = basename($err['file']);
                $detail['line'] = $err['line'];
            }

            echo json_encode([
                'success' => false,
                'data'    => array_merge(['message' => $message], $detail),
            ], JSON_UNESCAPED_UNICODE);
        });
    }

    /**
     * 发送 JSON 错误并退出.
     */
    private static function json_error($message, $status = 500)
    {
        wp_send_json_error(['message' => $message], $status);
    }
}
