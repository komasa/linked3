<?php

declare(strict_types=1);
namespace Linked3\Includes;
if (!defined('ABSPATH')) exit;
/**
 * Ajax guard.
 *
 * @package    Linked3
 * @subpackage Linked3.Includes
 * @since      27.1.0
 */
final class AjaxGuard
{
    public static function protect($nonce_action, $capability = 'edit_posts', $nonce_field = 'nonce') : bool { if (!check_ajax_referer($nonce_action, $nonce_field, false)) { wp_send_json_error(['message' => __('Security check failed.', 'linked3-ai')], 403); return false; } if (!current_user_can($capability)) { wp_send_json_error(['message' => __('Insufficient permissions.', 'linked3-ai')], 403); return false; } return true; }
}
