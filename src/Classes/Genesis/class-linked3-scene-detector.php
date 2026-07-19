<?php
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
/**
 * Linked3_Scene_Detector — G8 extraction.
 * @since 27.13.0
 */
class Linked3_Scene_Detector
{
    public static function ajax_get_axes()
    : void {
        check_ajax_referer('linked3_scene_axis', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足, 需要 edit_posts 能力。', 'linked3')], 403);
        }

        wp_send_json_success(self::get_all_axes());
    }

}
