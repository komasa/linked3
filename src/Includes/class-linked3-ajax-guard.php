<?php
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Ajax guard.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class Linked3_Ajax_Guard
{
    public static function protect($nonce_action, $capability = 'edit_posts', $nonce_field = 'nonce') : bool { if (!check_ajax_referer($nonce_action, $nonce_field, false)) { wp_send_json_error(['message' => __('Security check failed.', 'linked3-ai')], 403); return false; } if (!current_user_can($capability)) { wp_send_json_error(['message' => __('Insufficient permissions.', 'linked3-ai')], 403); return false; } return true; }
    public static function verify_nonce($nonce_action, $nonce_field = 'nonce') : bool { if (!check_ajax_referer($nonce_action, $nonce_field, false)) { wp_send_json_error(['message' => __('Security check failed.', 'linked3-ai')], 403); return false; } return true; }
    public static function check_cap($capability) : bool { if (!current_user_can($capability)) { wp_send_json_error(['message' => __('Insufficient permissions.', 'linked3-ai')], 403); return false; } return true; }
    public static function protect_with_rate_limit($nonce_action, $capability, $rate_limit_key, $max_requests = 100, $window = 3600) : bool { if (!self::protect($nonce_action, $capability)) return false; $user_id = get_current_user_id(); $bucket = "linked3_rl_{$rate_limit_key}_{$user_id}"; $data = get_transient($bucket); if ($data === false) $data = ['count' => 0, 'expires' => time() + $window]; if ($data['count'] >= $max_requests) { wp_send_json_error(['message' => __('Rate limit exceeded.', 'linked3-ai')], 429); return false; } $data['count']++; set_transient($bucket, $data, max(1, $data['expires'] - time() + 1)); return true; }
}
