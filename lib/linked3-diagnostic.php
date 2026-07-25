<?php
/**
 * Linked3 AI — Standalone Diagnostic Reader (v27.6.5)
 *
 * This file does NOT depend on the Linked3 plugin being active.
 * It reads the error option stored by wp-early-error-handler.php
 * and displays the last known activation/startup errors.
 *
 * USAGE:
 *   Visit:  https://your-site.com/wp-content/plugins/linked3/lib/linked3-diagnostic.php
 *   Or:     https://playground.wordpress.net/wp-content/plugins/linked3/lib/linked3-diagnostic.php
 *
 * It also reads wp-content/debug.log (last 100 lines) if available.
 *
 * @package Linked3
 * @since   27.6.5
 */

// Bootstrap WordPress minimally so we can access get_option.
define('SHORTINIT', true);
$wp_config = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-config.php';
if (!file_exists($wp_config)) {
    // Try alternate path (playground structure).
    $wp_config = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-config.php';
}
if (file_exists($wp_config)) {
    require_once $wp_config;
} else {
    http_response_code(500);
    echo 'Cannot locate wp-config.php';
    exit;
}

// v27.6.22-fix S-02: Access control — only allow logged-in admins.
// Without this, anyone could view plugin error logs and diagnostic info.
if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
    http_response_code(403);
    echo 'Access denied. Administrator privileges required.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
echo '<title>Linked3 AI Diagnostic</title>';
echo '<style>
body { font-family: -apple-system, sans-serif; margin: 40px; background: #f0f0f1; color: #1d2327; }
.box { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
.error { border-left: 4px solid #d63638; }
.ok { border-left: 4px solid #00a32a; }
code, pre { background: #f6f7f7; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
pre { padding: 12px; overflow-x: auto; }
h1 { font-size: 20px; }
h2 { font-size: 16px; margin-top: 0; }
.err-item { margin: 8px 0; padding: 8px; background: #fff8e5; border-radius: 3px; }
</style></head><body>';

echo '<h1>Linked3 AI — Diagnostic Report</h1>';
echo '<p>Version: 27.6.5 | PHP: ' . PHP_VERSION . ' | Time: ' . date('Y-m-d H:i:s') . '</p>';

// ── 1. WordPress environment ──
echo '<div class="box"><h2>WordPress Environment</h2>';
echo '<table cellpadding="4">';
echo '<tr><td>WP_VERSION</td><td>' . (defined('WP_VERSION') ? WP_VERSION : 'N/A') . '</td></tr>';
echo '<tr><td>ABSPATH</td><td>' . (defined('ABSPATH') ? ABSPATH : 'N/A') . '</td></tr>';
echo '<tr><td>WP_CONTENT_DIR</td><td>' . (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : 'N/A') . '</td></tr>';
echo '<tr><td>WP_DEBUG</td><td>' . (defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false') . '</td></tr>';
echo '<tr><td>Memory Limit</td><td>' . ini_get('memory_limit') . '</td></tr>';
echo '<tr><td>Max Execution</td><td>' . ini_get('max_execution_time') . 's</td></tr>';
echo '</table>';
echo '</div>';

// ── 2. Stored activation errors ──
echo '<div class="box ' . (function_exists('get_option') && get_option('linked3_early_errors') ? 'error' : 'ok') . '">';
echo '<h2>Stored Activation Errors</h2>';
if (function_exists('get_option')) {
    $errors = get_option('linked3_early_errors');
    if ($errors && !empty($errors['errors'])) {
        echo '<p>Found ' . count($errors['errors']) . ' error(s) from last activation attempt.</p>';
        echo '<p>Source: ' . htmlspecialchars($errors['source'] ?? 'unknown') . ' | Stored: ' . htmlspecialchars($errors['timestamp'] ?? 'unknown') . '</p>';
        foreach ($errors['errors'] as $err) {
            echo '<div class="err-item">';
            echo '<strong>[' . htmlspecialchars($err['type'] ?? 'Error') . ']</strong> ';
            echo htmlspecialchars($err['message'] ?? '');
            echo '<br>File: <code>' . htmlspecialchars($err['file'] ?? '') . ':' . htmlspecialchars($err['line'] ?? '?') . '</code>';
            if (isset($err['expected'])) {
                echo '<br>Expected: <code>' . htmlspecialchars($err['expected']) . '</code>';
            }
            echo '</div>';
        }
    } else {
        echo '<p>No stored errors. Plugin may have activated successfully, or errors were cleared.</p>';
    }
} else {
    echo '<p>get_option() not available — WordPress not fully loaded.</p>';
}
echo '</div>';

// ── 3. Plugin file check ──
echo '<div class="box"><h2>Plugin File Check</h2>';
$plugin_dir = dirname(dirname(__FILE__));
$main_file = $plugin_dir . '/linked3.php';
echo '<p>Plugin directory: <code>' . htmlspecialchars($plugin_dir) . '</code></p>';
echo '<p>Main file exists: ' . (file_exists($main_file) ? '✅ YES' : '❌ NO') . '</p>';
echo '<p>Handler exists: ' . (file_exists($plugin_dir . '/lib/wp-early-error-handler.php') ? '✅ YES' : '❌ NO') . '</p>';
echo '<p>Autoload exists: ' . (file_exists($plugin_dir . '/src/autoload.php') ? '✅ YES' : '❌ NO') . '</p>';
echo '<p>Plugin.php exists: ' . (file_exists($plugin_dir . '/src/Includes/Plugin.php') ? '✅ YES' : '❌ NO') . '</p>';
echo '</div>';

// ── 4. debug.log (last 100 lines) ──
echo '<div class="box"><h2>debug.log (last 100 lines)</h2>';
if (defined('WP_CONTENT_DIR')) {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file) && is_readable($log_file)) {
        $lines = file($log_file);
        $tail = array_slice($lines, -100);
        echo '<pre>' . htmlspecialchars(implode('', $tail)) . '</pre>';
    } else {
        echo '<p>debug.log not found or not readable at: <code>' . htmlspecialchars($log_file) . '</code></p>';
        echo '<p>To enable: add <code>define(\'WP_DEBUG\', true); define(\'WP_DEBUG_LOG\', true);</code> to wp-config.php</p>';
    }
} else {
    echo '<p>WP_CONTENT_DIR not defined.</p>';
}
echo '</div>';

// ── 5. PHP loaded extensions ──
echo '<div class="box"><h2>PHP Extensions</h2>';
$required = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'xml', 'zip'];
foreach ($required as $ext) {
    echo $ext . ': ' . (extension_loaded($ext) ? '✅' : '❌') . ' ';
}
echo '</div>';

echo '<p style="font-size:12px;color:#646970;">Generated by Linked3 AI v27.6.5 standalone diagnostic.</p>';
echo '</body></html>';
