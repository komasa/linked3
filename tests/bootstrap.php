<?php
/**
 * PHPUnit bootstrap — stubs minimal WordPress functions for unit tests.
 *
 * @package Linked3
 */

// Prevent actual WordPress loading
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/fake-abspath/');
}

// Stub common WP functions used across the codebase
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) { return $content; }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) { return $value; }
}
if (!function_exists('do_action')) {
    function do_action($tag, ...$args) { /* no-op */ }
}
if (!function_exists('get_option')) {
    function get_option($name, $default = false) { return $default; }
}
if (!function_exists('update_option')) {
    function update_option($name, $value) { return true; }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return dirname($file) . '/'; }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) { return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/'; }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { /* no-op */ }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { /* no-op */ }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(...$args) { /* no-op */ }
}
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(...$args) { /* no-op */ }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) { return true; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) { return 'fake-nonce'; }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) { echo json_encode(['success' => true, 'data' => $data]); }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) { echo json_encode(['success' => false, 'data' => $data]); }
}
if (!function_exists('wp_die')) {
    function wp_die($message = '') { throw new RuntimeException($message); }
}

// Autoload plugin classes via composer if available
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
