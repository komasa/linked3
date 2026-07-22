<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads Composer autoloader and defines minimal WordPress stubs
 * so that unit tests can run without a full WordPress installation.
 *
 * @package Linked3\Test
 */

declare(strict_types=1);

// 1. Composer autoloader (dev dependencies: PHPUnit, etc.)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

// 2. Project autoloader (PSR-4: Linked3\ → src/)
$projectAutoload = __DIR__ . '/../src/autoload.php';
if (file_exists($projectAutoload)) {
    require $projectAutoload;
}

// 3. Define ABSPATH so that `if (!defined('ABSPATH')) exit;` guards pass
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// 4. Minimal WordPress function stubs for testing without WP
//    Only stubs that are actually called by the classes under test.
//    As the test suite grows, expand this section as needed.

if (!function_exists('__')) {
    function __($text, $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($input): string {
        return is_string($input) ? trim(strip_tags($input)) : '';
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = ''): bool {
        // Test environment: always return true for non-empty nonce
        return $nonce !== '';
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool {
        // Test environment: grant all capabilities by default
        return true;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data, int $status_code = 500): void {
        wp_send_json(['success' => false, 'data' => $data], $status_code);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, int $status_code = 200): void {
        wp_send_json(['success' => true, 'data' => $data], $status_code);
    }
}

if (!function_exists('wp_send_json')) {
    /**
     * Capture JSON output instead of echoing+exiting.
     * Test harness can inspect $GLOBALS['__test_json_output'].
     */
    function wp_send_json(array $response, int $status_code = 200): void {
        $GLOBALS['__test_json_output'] = $response;
        $GLOBALS['__test_json_status'] = $status_code;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $name, $default = false) {
        // Test environment: return defaults
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $name, $value): bool {
        $GLOBALS['__test_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $tag, ...$args): void {
        // No-op in test environment
    }
}

if (!function_exists('add_action')) {
    function add_action(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []): void {
        throw new RuntimeException('wp_die called: ' . (is_string($message) ? $message : ''));
    }
}
