<?php

declare(strict_types=1);
/**
 * WC + Forms + Speech hooks registrar.
 *
 * @package Linked3
 * @subpackage Classes\WooCommerce
 */

namespace Linked3\Classes\WooCommerce;

use Linked3\Classes\AIForms\AiFormManager;
use Linked3\Classes\Speech\TtsManager;


if (!defined('ABSPATH')) {
    exit;
}
use Linked3\Classes\STT\SttManager; // phpcs:ignore -- reserved for future STT feature
use Linked3\Includes\Traits\{TraitCheckAdminPermissions, TraitCheckPlanAccess, TraitSendWPError};

final class WcFormsSpeechHooksRegistrar
{
    static function register(): void {
        // AI Forms.
        AiFormManager::register();
        // v1.0.0 FINAL-AUDIT: wire up admin CRUD AJAX (create/update/delete).
        // Previously the forms admin page was read-only; admins had to write
        // PHP code to create forms.
        AiFormManager::register_admin_ajax();
        add_action('wp_ajax_nopriv_linked3_form_submit', [AiFormManager::class, 'handle_submission']);
        add_action('wp_ajax_linked3_form_submit', [AiFormManager::class, 'handle_submission']);

        // TTS shortcode.
        TtsManager::register_shortcode();

        // WC AJAX (admin only).
        add_action('wp_ajax_linked3_wc_generate_desc', [__CLASS__, 'wc_generate_desc']);
        add_action('wp_ajax_linked3_wc_generate_reviews', [__CLASS__, 'wc_generate_reviews']);
        // v1.0.0 FINAL-AUDIT: wire up generate_image (was implemented but
        // never registered as an AJAX action).
        add_action('wp_ajax_linked3_wc_generate_image', [__CLASS__, 'wc_generate_image']);

        // TTS AJAX (frontend).
        add_action('wp_ajax_linked3_tts_synthesize', [__CLASS__, 'tts_synthesize']);
        add_action('wp_ajax_nopriv_linked3_tts_synthesize', [__CLASS__, 'tts_synthesize']);

        // Admin menu.
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
    }

    static function wc_generate_desc(): void {
        self::verify_admin('linked3_wc');
        self::require_pro();
        $ids = array_filter(array_map('intval', (array) (wp_unslash($_POST['product_ids'] ?? []))));
        if (empty($ids)) wp_send_json_error(['message' => __('未选择商品。', 'linked3')], 400);
        $gen = new WcAiGenerator();
        $result = $gen->generate_descriptions($ids, [
            'tone' => sanitize_text_field($_POST['tone'] ?? 'persuasive'),
            'language' => sanitize_text_field($_POST['language'] ?? 'zh-CN'),
            'provider' => sanitize_text_field($_POST['provider'] ?? 'openai'),
            'model' => sanitize_text_field($_POST['model'] ?? 'gpt-4o-mini'),
        ]);
        $result['ok'] ? wp_send_json_success($result) : wp_send_json_error($result, 502);
    }

    static function wc_generate_reviews(): void {
        self::verify_admin('linked3_wc');
        self::require_pro();
        $pid = (int) ($_POST['product_id'] ?? 0);
        $count = min(10, (int) ($_POST['count'] ?? 3));
        if (!$pid) wp_send_json_error(['message' => __('需要商品 ID。', 'linked3')], 400);
        $gen = new WcAiGenerator();
        $result = $gen->generate_reviews($pid, $count);
        $result['ok'] ? wp_send_json_success($result) : wp_send_json_error($result, 403);
    }

    /**
     * AJAX: generate AI product image (DALL-E 3). Pro+ feature.
     * v1.0.0 FINAL-AUDIT: wire up the previously-orphaned generator method.
     */
    static function wc_generate_image(): void {
        self::verify_admin('linked3_wc');
        self::require_pro();
        $pid = (int) ($_POST['product_id'] ?? 0);
        if (!$pid) wp_send_json_error(['message' => __('需要商品 ID。', 'linked3')], 400);
        $gen = new WcAiGenerator();
        $result = $gen->generate_image($pid, [
            'provider' => sanitize_text_field($_POST['provider'] ?? 'openai'),
            'model'    => sanitize_text_field($_POST['model'] ?? 'dall-e-3'),
            'size'     => sanitize_text_field($_POST['size'] ?? '1024x1024'),
            'quality'  => sanitize_text_field($_POST['quality'] ?? 'standard'),
        ]);
        $result['ok'] ? wp_send_json_success($result) : wp_send_json_error($result, 502);
    }

    static function tts_synthesize(): void {
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_tts')) {
            wp_send_json_error(['message' => __('安全校验失败。', 'linked3')], 403);
        }
        // v2.9.0: 移除 plan gate,本地模式所有用户可用 TTS
        // Rate limit TTS (10/hour/user).
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        $bucket = 'linked3_tts_rl_' . md5($ip);
        $count = (int) get_transient($bucket);
        if ($count >= 10) wp_send_json_error(['message' => __('TTS 速率限制。', 'linked3')], 429);
        set_transient($bucket, $count + 1, HOUR_IN_SECONDS);

        $text = sanitize_textarea_field($_POST['text'] ?? '');
        $voice = sanitize_text_field($_POST['voice'] ?? 'alloy');
        if (empty($text)) wp_send_json_error(['message' => __('文本为空。', 'linked3')], 400);
        $tts = new \Linked3\Classes\Speech\TtsManager();
        $config = [
            'provider' => sanitize_text_field($_POST['provider'] ?? 'openai'),
            'api_key' => '',
            'model' => sanitize_text_field($_POST['model'] ?? 'tts-1'),
        ];
        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $config['api_key'] = is_array($keys) && isset($keys[$config['provider']]) ? $keys[$config['provider']] : '';
        $result = $tts->synthesize($text, $voice, $config);
        $result['ok'] ? wp_send_json_success($result) : wp_send_json_error($result, 502);
    }

    static function verify_admin($nonce_action): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限。', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, $nonce_action)) wp_send_json_error(['message' => __('安全校验失败。', 'linked3')], 403);
    }

    static function require_pro(): void {
        // v2.9.0: 移除 plan gate,本地模式所有用户可用
        // 保留方法签名以兼容现有调用点
    }

    static function register_admin_menu(): void {
        // 商品AI 不再依赖 WooCommerce — 即使没装 WC 也能显示页面
        add_submenu_page('linked3-dashboard', '商品AI', '商品AI', 'manage_options', 'linked3-wc', [__CLASS__, 'render_wc_page']);
        add_submenu_page('linked3-dashboard', 'AI表单', 'AI表单', 'manage_options', 'linked3-forms', [__CLASS__, 'render_forms_page']);
        add_submenu_page('linked3-dashboard', '语音TTS/STT', '语音TTS/STT', 'manage_options', 'linked3-speech', [__CLASS__, 'render_speech_page']);
    }

    static function render_wc_page(): void {
        if (!current_user_can('manage_options')) return;
        include LINKED3_DIR . 'admin/views/wc/dashboard.php';
    }
    static function render_forms_page(): void {
        if (!current_user_can('manage_options')) return;
        include LINKED3_DIR . 'admin/views/forms/dashboard.php';
    }
    static function render_speech_page(): void {
        if (!current_user_can('manage_options')) return;
        include LINKED3_DIR . 'admin/views/speech/dashboard.php';
    }
}
