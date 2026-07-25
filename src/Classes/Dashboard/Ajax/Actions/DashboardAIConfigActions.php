<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Dashboard\DashboardConfigAjax;
if (!defined('ABSPATH')) exit;

/**
 * Dashboard aiconfig actions.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 */

class DashboardAIConfigActions extends DashboardBaseAjaxAction
{
    static function register(): void {
        add_action('wp_ajax_linked3_sync_models', [__CLASS__, 'sync_models']);
        add_action('wp_ajax_linked3_save_ai_suffix', [__CLASS__, 'save_ai_suffix']);
        add_action('wp_ajax_linked3_save_advanced', [__CLASS__, 'save_advanced']);
        add_action('wp_ajax_linked3_save_custom_apis', [__CLASS__, 'save_custom_apis']);
        add_action('wp_ajax_linked3_save_provider_config', [__CLASS__, 'save_provider_config']);
        add_action('wp_ajax_linked3_save_seo_enhance', [__CLASS__, 'save_seo_enhance']);
        add_action('wp_ajax_linked3_save_image_settings', [__CLASS__, 'save_image_settings']);
        add_action('wp_ajax_linked3_test_image_station', [__CLASS__, 'test_image_station']);
        add_action('wp_ajax_linked3_sync_image_models', [__CLASS__, 'sync_image_models']);
        add_action('wp_ajax_linked3_save_geo', [__CLASS__, 'save_geo']);
        add_action('wp_ajax_linked3_save_ai_search_keys', [__CLASS__, 'save_ai_search_keys']);
        add_action('wp_ajax_linked3_regen_llms_txt', [__CLASS__, 'regen_llms_txt']);
        // v27.8.11 (审计Phase1): 新增测试连接端点
        add_action('wp_ajax_linked3_test_provider', [__CLASS__, 'test_provider']);
    }


    /**
     * Action: wp_ajax_linked3_sync_models
     * Implementation: DashboardConfigAjax::ajax_sync_models()
     */
    public static function sync_models()  : void { DashboardConfigAjax::ajax_sync_models(); }

    /**
     * Action: wp_ajax_linked3_save_ai_suffix
     * Implementation: DashboardConfigAjax::ajax_save_ai_suffix()
     */
    public static function save_ai_suffix()  : void { DashboardConfigAjax::ajax_save_ai_suffix(); }

    /**
     * Action: wp_ajax_linked3_save_advanced
     * Implementation: DashboardConfigAjax::ajax_save_advanced()
     */
    public static function save_advanced()
     : void { DashboardConfigAjax::ajax_save_advanced(); }

    /**
     * Action: wp_ajax_linked3_save_custom_apis
     * Implementation: DashboardConfigAjax::ajax_save_custom_apis()
     */
    public static function save_custom_apis()
     : void { DashboardConfigAjax::ajax_save_custom_apis(); }

    /**
     * Action: wp_ajax_linked3_save_provider_config
     * Implementation: DashboardConfigAjax::ajax_save_provider_config()
     */
    public static function save_provider_config()
     : void { DashboardConfigAjax::ajax_save_provider_config(); }

    /**
     * Action: wp_ajax_linked3_save_seo_enhance
     * Implementation: DashboardConfigAjax::ajax_save_seo_enhance()
     */
    public static function save_seo_enhance()
     : void { DashboardConfigAjax::ajax_save_seo_enhance(); }

    /**
     * Action: wp_ajax_linked3_save_image_settings
     * Implementation: DashboardConfigAjax::ajax_save_image_settings()
     */
    public static function save_image_settings()
     : void { DashboardConfigAjax::ajax_save_image_settings(); }

    /**
     * Action: wp_ajax_linked3_test_image_station
     * Implementation: DashboardConfigAjax::ajax_test_image_station()
     */
    public static function test_image_station()
     : void { DashboardConfigAjax::ajax_test_image_station(); }

    /**
     * Action: wp_ajax_linked3_sync_image_models
     * Implementation: DashboardConfigAjax::ajax_sync_image_models()
     */
    public static function sync_image_models()
     : void { DashboardConfigAjax::ajax_sync_image_models(); }

    /**
     * Action: wp_ajax_linked3_save_geo
     * Ghost method: never had an implementation.
     */
    public static function save_geo()
    {
        wp_send_json_error([
            'message' => __('AJAX endpoint "ajax_save_geo" is not implemented. This is a known issue from PSR-4 migration.', 'linked3'),
            'code' => 'ghost_method',
        ], 501);
    }

    /**
     * Action: wp_ajax_linked3_save_ai_search_keys
     * Implementation: DashboardConfigAjax::ajax_save_ai_search_keys()
     */
    public static function save_ai_search_keys()
     : void { DashboardConfigAjax::ajax_save_ai_search_keys(); }

    /**
     * Action: wp_ajax_linked3_regen_llms_txt
     * Implementation: DashboardConfigAjax::ajax_regen_llms_txt()
     */
    public static function regen_llms_txt()
     : void { DashboardConfigAjax::ajax_regen_llms_txt(); }

    /**
     * v27.8.11 (审计Phase1): 测试 Provider 连接
     *
     * POST: provider (slug), api_key (可选, 不传则用已保存的), api_base (可选), model (可选)
     * 发送一个简单的 "Hello" 请求验证 API Key 是否有效
     */
    public static function test_provider(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        }
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_settings')) {
            wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);
        }

        $provider = sanitize_key($_POST['provider'] ?? '');
        if (empty($provider)) {
            wp_send_json_error(['message' => __('缺少 provider 参数', 'linked3')], 400);
        }

        // 读取 API Key: 优先用 POST 传的 (未保存的), 其次用已保存的
        $api_key = '';
        if (!empty($_POST['api_key'])) {
            $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
        } else {
            $saved_keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
            if (!empty($saved_keys[$provider])) {
                // 解密已保存的 key
                $raw_keys = array_filter(array_map('trim', explode("\n", $saved_keys[$provider])));
                if (!empty($raw_keys[0])) {
                    $decrypted = \Linked3\Includes\Crypto::decrypt((string) $raw_keys[0]);
                    $api_key = $decrypted !== '' ? $decrypted : $raw_keys[0];
                }
            }
        }

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => sprintf(__('Provider "%s" 未配置 API Key, 请先填写并保存', 'linked3'), $provider),
                'code' => 'no_api_key',
            ], 400);
        }

        // 读取 model: 优先用 POST 传的, 其次用已保存的, 最后用默认
        $model = '';
        if (!empty($_POST['model'])) {
            $model = sanitize_text_field($_POST['model']);
        } else {
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $saved_models[$provider] ?? '';
        }
        if (empty($model)) {
            $provider_defaults = [
                'openai' => 'gpt-4o-mini', 'deepseek' => 'deepseek-chat',
                'kimi' => 'moonshot-v1-8k', 'qwen' => 'qwen-plus',
                'zhipu' => 'glm-4-flash', 'zai' => 'glm-4-flash',
                'siliconflow' => 'Qwen/Qwen2.5-7B-Instruct',
            ];
            $model = $provider_defaults[$provider] ?? 'gpt-4o-mini';
        }

        // 读取 api_base
        $api_base = '';
        if (!empty($_POST['api_base'])) {
            $api_base = esc_url_raw($_POST['api_base']);
        } else {
            $saved_bases = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_api_bases', []);
            $api_base = $saved_bases[$provider] ?? '';
        }

        // 发送测试请求
        @set_time_limit(30);
        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            $result = $dispatcher->chat(
                [
                    ['role' => 'user', 'content' => '请回复"连接成功"四个字'],
                ],
                [
                    'provider' => $provider,
                    'model' => $model,
                    'max_tokens' => 20,
                    'temperature' => 0,
                    'timeout' => 15,
                    'module' => 'test_provider',
                ],
                [
                    'api_key' => $api_key,
                    'api_base' => $api_base,
                    'fallback_providers' => [], // 不 fallback, 只测试指定 provider
                    'force_bypass_circuit' => true,
                ]
            );

            if (!empty($result['content'])) {
                wp_send_json_success([
                    'ok' => true,
                    'message' => sprintf(__('✅ 连接成功! Provider: %s, Model: %s, 响应: %s', 'linked3'), $provider, $model, mb_substr($result['content'], 0, 50)),
                    'provider' => $provider,
                    'model' => $model,
                    'response_preview' => mb_substr($result['content'], 0, 100),
                ]);
            } elseif (!empty($result['error'])) {
                wp_send_json_error([
                    'message' => sprintf(__('❌ 连接失败: %s', 'linked3'), $result['error']),
                    'code' => 'api_error',
                    'provider' => $provider,
                    'model' => $model,
                ], 500);
            } else {
                wp_send_json_error([
                    'message' => __('❌ 连接失败: AI 返回空响应', 'linked3'),
                    'code' => 'empty_response',
                    'provider' => $provider,
                    'model' => $model,
                ], 500);
            }
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => sprintf(__('❌ 连接异常: %s', 'linked3'), $e->getMessage()),
                'code' => 'exception',
                'provider' => $provider,
                'model' => $model,
            ], 500);
        }
    }
}
