<?php

declare(strict_types=1);
/**
 * EcosystemCronTrait — Cron control + long-form/chart delegates + image API.
 *
 * @package Linked3\Content
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

trait EcosystemCronTrait
{
    /**
     * v10.7.4 保存图片生成API设置
     */
    public static function ajax_save_image_api() : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $provider = sanitize_key($_POST['provider'] ?? 'siliconflow');
        $model = sanitize_text_field($_POST['model'] ?? 'Kwai-Kolors/Kolors');
        $api_base = esc_url_raw($_POST['api_base'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $width = max(256, min(4096, intval($_POST['width'] ?? 1024)));
        $height = max(256, min(4096, intval($_POST['height'] ?? 1024)));

        update_option(LINKED3_OPTION_PREFIX . 'image_provider', $provider);
        update_option(LINKED3_OPTION_PREFIX . 'image_model', $model);
        update_option(LINKED3_OPTION_PREFIX . 'image_api_base', $api_base);
        update_option(LINKED3_OPTION_PREFIX . 'image_api_key', $api_key);
        update_option(LINKED3_OPTION_PREFIX . 'image_width', $width);
        update_option(LINKED3_OPTION_PREFIX . 'image_height', $height);

        wp_send_json_success(['message' => __('图片API设置已保存', 'linked3')]);
    }

    /**
     * v10.7.4 实际生成图片 — SOP闭环下一步
     */
    public static function ajax_generate_images() : void { EcosystemAjaxAdvanced::ajax_generate_images(); }

    /**
     * v10.7.1 长文写作 — 生成大纲
     */
    public static function ajax_longform_outline() : void { EcosystemAjaxAdvanced::ajax_longform_outline(); }

    /**
     * v10.7.1 长文写作 — 生成单段
     */
    public static function ajax_longform_section() : void { EcosystemAjaxAdvanced::ajax_longform_section(); }

    /**
     * v10.7.1 CSV批量写作
     */
    public static function ajax_csv_batch() : void { EcosystemAjaxAdvanced::ajax_csv_batch(); }

    /**
     * v10.7.1 定时任务启用
     */
    public static function ajax_cron_enable() : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $freq = sanitize_key($_POST['freq'] ?? 'daily');
        $count = max(1, min(20, intval($_POST['count'] ?? 3)));

        // 注册定时任务
        $hook = 'linked3_eco_cron_auto_generate';
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $freq, $hook, [$count]);
        }
        update_option(LINKED3_OPTION_PREFIX . 'cron_settings', [
            'enabled' => true,
            'freq' => $freq,
            'count' => $count,
            'updated_at' => current_time('mysql'),
        ], false);

        wp_send_json_success([
            'message' => __('定时任务已启用: ', 'linked3') . $freq . ' 生成' . $count . '篇',
            'freq' => $freq,
            'count' => $count,
        ]);
    }

    /**
     * v10.7.1 定时任务禁用
     */
    public static function ajax_cron_disable() : void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $hook = 'linked3_eco_cron_auto_generate';
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        update_option(LINKED3_OPTION_PREFIX . 'cron_settings', ['enabled' => false], false);

        wp_send_json_success(['message' => __('定时任务已禁用', 'linked3')]);
    }
}
