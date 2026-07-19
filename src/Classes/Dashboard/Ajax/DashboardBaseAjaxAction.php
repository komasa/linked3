<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax;
if (!defined('ABSPATH')) exit;

/**
 * Dashboard base ajax action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax
 * @since      27.1.0
 */

abstract class DashboardBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_dashboard';
    const REQUIRED_CAP = 'manage_options';

    abstract public static function register();

    protected static function verify_request()
    : bool {
        if (!check_ajax_referer(static::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => __('Nonce verification failed.', 'linked3-ai')], 403);
            return false;
        }
        if (!current_user_can(static::REQUIRED_CAP)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'linked3-ai')], 403);
            return false;
        }
        return true;
    }

    protected static function get_param($key, $default = '', $type = 'text') : mixed {
        $value = $_POST[$key] ?? $default;
        switch ($type) {
            case 'int': return (int) $value;
            case 'bool': return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array': return is_array($value) ? array_map('sanitize_text_field', wp_unslash($value)) : [];
            case 'textarea': return sanitize_textarea_field(wp_unslash($value));
            default: return sanitize_text_field(wp_unslash($value));
        }
    }

    protected static function send_success($data = []) : void { wp_send_json_success($data); }
    protected static function send_error($message, $code = 400) : void { wp_send_json_error(['message' => $message], $code); }
}
