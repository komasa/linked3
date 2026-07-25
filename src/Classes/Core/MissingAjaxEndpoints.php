<?php

declare(strict_types=1);
/**
 * Missing AJAX Endpoints Registrar v27.8.5
 *
 * 注册前端调用但后端缺失的 AJAX 端点。
 * 每个端点返回明确的错误信息 (而非 Fatal Error),让前端能正常处理。
 *
 * 这些端点对应的处理方法尚未实现,但前端代码已引用。
 * 注册桩实现可以防止 "Failed to fetch" / 400 Bad Request 错误,
 * 让用户看到明确的 "功能开发中" 提示。
 *
 * @package Linked3
 * @subpackage Classes\Core
 * @since 27.8.5
 */

namespace Linked3\Classes\Core;

if (!defined('ABSPATH')) {
    exit;
}

class MissingAjaxEndpoints
{
    /**
     * 注册所有缺失的 AJAX 端点。
     *
     * v27.8.6: 经全量审计, 大部分端点已由各模块 Registrar 动态注册:
     *   - ContentWriterHooksRegistrar: linked3_generate_content/title/meta/tags
     *   - SEOHooksRegistrar: linked3_push_now/retry, linked3_seo_score
     *   - OSCapabilityStagesAjax: linked3_nengzhi_detect/stages
     *   - OSOnboardingAjax: linked3_ruliu_plan/status/update
     *   - OSConsciousnessAjax: linked3_frequency_assign
     *   - PublishCollectHooksRegistrar: linked3_collect_bulk_rewrite
     *
     * 仅 reverse_parse 和 svg_stats 无任何 Registrar 注册, 由本类委托到 V18。
     */
    public static function register(): void
    {
        // V18/OS: 逆向解析/SVG统计 — 委托到 V18 Facade
        add_action('wp_ajax_linked3_reverse_parse',      [__CLASS__, 'delegate_to_v18']);
        add_action('wp_ajax_linked3_svg_stats',          [__CLASS__, 'delegate_to_v18']);
    }

    /**
     * 通用 "功能开发中" 响应。
     * 返回 JSON 错误,让前端能正常处理 (而非 Failed to fetch)。
     */
    public static function not_implemented(): void
    {
        $action = sanitize_text_field($_REQUEST['action'] ?? 'unknown');
        wp_send_json_error([
            'message' => sprintf(
                __('功能 "%s" 正在开发中, 请使用其他功能或等待后续版本。', 'linked3'),
                $action
            ),
            'code'    => 'not_implemented',
            'action'  => $action,
        ], 501);
    }

    /**
     * 委托到 V18 Facade — reverse_parse 和 svg_stats 有实现但未注册为 AJAX。
     * v27.8.6: 修正参数名对齐前端 (json_raw/engineer_type, 非json/type)
     */
    public static function delegate_to_v18(): void
    {
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3-ai')], 403);
        }
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('无权限', 'linked3-ai')], 403);
        }

        $action = sanitize_text_field($_REQUEST['action'] ?? '');

        if ($action === 'linked3_reverse_parse' && class_exists('\\Linked3\\Classes\\OS\\V18')) {
            // v27.8.6: 前端传 json_raw + engineer_type (tab-v18.php:391-392)
            $json = wp_unslash($_POST['json_raw'] ?? $_POST['json'] ?? '');
            $type = sanitize_key($_POST['engineer_type'] ?? $_POST['type'] ?? '');
            try {
                $result = \Linked3\Classes\OS\V18::reverse_parse($json, $type);
                wp_send_json_success(['result' => $result, 'data' => $result]);
            } catch (\Throwable $e) {
                wp_send_json_error(['message' => $e->getMessage()], 500);
            }
        }

        if ($action === 'linked3_svg_stats' && class_exists('\\Linked3\\Classes\\OS\\V18')) {
            // v27.8.6: 前端不传 chart_type, 传空即可获取全部统计
            $chart_type = sanitize_key($_POST['chart_type'] ?? $_POST['type'] ?? '');
            try {
                $result = \Linked3\Classes\OS\V18::svg_stats($chart_type);
                wp_send_json_success(['result' => $result, 'data' => $result]);
            } catch (\Throwable $e) {
                wp_send_json_error(['message' => $e->getMessage()], 500);
            }
        }

        // Fallback
        self::not_implemented();
    }
}

// 在 init 时注册
add_action('init', [__NAMESPACE__ . '\\MissingAjaxEndpoints', 'register'], 20);
