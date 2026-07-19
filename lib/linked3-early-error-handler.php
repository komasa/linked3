<?php
/**
 * Linked3 AI — Ultra-Early Error Handler (v3 — Activation-Safe Edition)
 *
 * This file MUST be the very first thing loaded by linked3.php (before any
 * require_once that could fail).
 *
 * KEY FIXES IN v3:
 *   - FIXED CRITICAL BUG: v2 called batch_scan() BEFORE its definition.
 *     PHP conditional functions (inside `if (!function_exists())`) are NOT
 *     hoisted, so this caused "Call to undefined function" fatal — which
 *     prevented the batch scan from ever running.
 *   - ACTIVATION-SAFE: WordPress's activate_plugin() uses ob_start()/
 *     ob_end_clean() which discards all output. v3 stores errors in a
 *     transient and displays them via admin_notices on the next page load.
 *   - All function definitions now come BEFORE any calls.
 *
 * @package Linked3
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('LINKED3_EARLY_ERROR_HANDLER_LOADED')) {
    return;
}
define('LINKED3_EARLY_ERROR_HANDLER_LOADED', true);

if (!defined('LINKED3_EARLY_HANDLER_PLUGIN_DIR')) {
    define('LINKED3_EARLY_HANDLER_PLUGIN_DIR', dirname(__DIR__));
}

// =============================================================================
// ALL FUNCTION DEFINITIONS COME FIRST.

// Symbol analyzer + error renderer extracted to separate files
require_once __DIR__ . '/linked3-symbol-analyzer.php';
require_once __DIR__ . '/linked3-error-renderer.php';

// (PHP conditional functions are NOT hoisted — they must be defined before use.)
// =============================================================================

if (!function_exists('linked3_early_error_type_name')) {
    function linked3_early_error_type_name($type)
    {
        $map = [
            E_ERROR             => 'E_ERROR (Fatal Error)',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE (Parse Error)',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        ];
        return isset($map[$type]) ? $map[$type] : ('Error type #' . (int) $type);
    }
}

if (!function_exists('linked3_early_handler_is_ajax')) {
    function linked3_early_handler_is_ajax()
    : bool {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }
        if (function_exists('did_action') && did_action('wp_ajax_')) {
            return true;
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_is_activation')) {
    /**
     * Detect if we're inside WordPress's plugin activation flow.
     * During activation, output is captured by ob_start()/ob_end_clean(),
     * so we must store errors in a transient instead of printing them.
     */
    function linked3_early_handler_is_activation()
    : bool {
        // Check $_GET['action'] == 'activate' on plugins.php
        if (is_admin() && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->base === 'plugins') {
                if (isset($_GET['action']) && $_GET['action'] === 'activate') {
                    return true;
                }
            }
        }
        // Check if activate_plugin() is in the call stack
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);
        foreach ($bt as $frame) {
            if (isset($frame['function']) && $frame['function'] === 'activate_plugin') {
                return true;
            }
        }
        // Fallback: check request URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, 'plugins.php') !== false && strpos($uri, 'action=activate') !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_relpath')) {
    function linked3_early_handler_relpath($path, $base)
    {
        $rel = substr($path, strlen($base));
        $rel = ltrim($rel, '/\\');
        return $rel;
    }
}

if (!function_exists('linked3_early_handler_find_namespace_line')) {
    function linked3_early_handler_find_namespace_line($source)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], '<?php') !== false) {
                $php_started = true;
            }
            if (!$php_started) {
                continue;
            }
            if (preg_match('/^\s*namespace\s+[\\\\]?[\w\\\\]+\s*;/', $lines[$i])) {
                return $i;
            }
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_find_statement_before_namespace')) {
    function linked3_early_handler_find_statement_before_namespace($source, $namespace_line)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        $in_doc_block = false;
        for ($i = 0; $i < $namespace_line; $i++) {
            if (strpos($lines[$i], '<?php') !== false) {
                $php_started = true;
                continue;
            }
            if (!$php_started) {
                continue;
            }
            $trimmed = trim($lines[$i]);
            if ($trimmed === '') {
                continue;
            }
            if ($in_doc_block) {
                if (substr(rtrim($trimmed), -2) === '*/') {
                    $in_doc_block = false;
                }
                continue;
            }
            if (strpos($trimmed, '/**') === 0) {
                if (substr(rtrim($trimmed), -2) !== '*/') {
                    $in_doc_block = true;
                }
                continue;
            }
            if (strpos($trimmed, '/*') === 0) {
                if (substr(rtrim($trimmed), -2) !== '*/') {
                    $in_doc_block = true;
                }
                continue;
            }
            if (strpos($trimmed, '//') === 0) {
                continue;
            }
            if (strpos($trimmed, '#') === 0) {
                continue;
            }
            if (strpos($trimmed, '*') === 0) {
                continue;
            }
            if (substr(rtrim($trimmed), -2) === '*/') {
                continue;
            }
            if (preg_match('/^declare\s*\(/', $trimmed)) {
                continue;
            }
            return $i;
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_batch_scan')) {
    /**
     * Scan every .php file in $plugin_dir for syntax errors.
     * Returns an array of errors.
     */
    function linked3_early_handler_batch_scan($plugin_dir, $skip_dirs = [])
    {
        $errors = [];

        if (!is_dir($plugin_dir)) {
            return $errors;
        }

        $skip_paths = [];
        foreach ($skip_dirs as $d) {
            $skip_paths[] = $plugin_dir . '/' . $d;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($plugin_dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
        } catch (Throwable $e) {
            return $errors;
        }

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            $skip = false;
            foreach ($skip_paths as $skip_path) {
                if (strpos($path, $skip_path) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $source = @file_get_contents($path);
            if ($source === false) {
                continue;
            }

            $rel = linked3_early_handler_relpath($path, $plugin_dir);

            // Method 1: token_get_all with TOKEN_PARSE.
            try {
                token_get_all($source, TOKEN_PARSE);
            } catch (ParseError $e) {
                $errors[] = [
                    'type'    => 'ParseError',
                    'message' => $e->getMessage(),
                    'file'    => $rel,
                    'line'    => $e->getLine(),
                ];
                continue;
            } catch (Error $e) {
                $errors[] = [
                    'type'    => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $rel,
                    'line'    => $e->getLine(),
                ];
                continue;
            } catch (Throwable $e) {
                $errors[] = [
                    'type'    => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $rel,
                    'line'    => $e->getLine(),
                ];
                continue;
            }

            // Method 2: regex check for "use before namespace".
            $ns_line = linked3_early_handler_find_namespace_line($source);
            if ($ns_line !== false) {
                $bad_line = linked3_early_handler_find_statement_before_namespace($source, $ns_line);
                if ($bad_line !== false) {
                    $lines = explode("\n", $source);
                    $bad_content = trim($lines[$bad_line]);
                    $is_use = (strpos($bad_content, 'use ') === 0);
                    $errors[] = [
                        'type'    => $is_use ? 'UseBeforeNamespace' : 'StatementBeforeNamespace',
                        'message' => $is_use
                            ? 'use statement appears before namespace declaration. use must come AFTER namespace. (Namespace is on line ' . ($ns_line + 1) . ')'
                            : 'Statement appears before namespace declaration (line ' . ($ns_line + 1) . '): "' . substr($bad_content, 0, 100) . '". Namespace must be the first statement or after declare().',
                        'file'    => $rel,
                        'line'    => $bad_line + 1,
                    ];
                }
            }
        }

        return $errors;
    }
}

// linked3_early_handler_tokenize_safe moved to extracted file

// linked3_early_handler_extract_symbol_info moved to extracted file

// linked3_early_handler_resolve_fqcn moved to extracted file

// linked3_early_handler_symbol_scan moved to extracted file

if (!function_exists('linked3_early_handler_store_errors')) {
    /**
     * Store errors in a transient so they survive redirects (e.g. during
     * plugin activation, where output is captured by ob_start/ob_end_clean).
     */
    function linked3_early_handler_store_errors($errors, $source = 'batch_scan')
    : void {
        if (empty($errors)) {
            return;
        }
        $data = [
            'source'   => $source,
            'time'     => time(),
            'errors'   => $errors,
            'count'    => count($errors),
            'plugin'   => 'Linked3 AI',
            'version'  => defined('LINKED3_VERSION') ? LINKED3_VERSION : 'unknown',
        ];
        // Use both transient and option for redundancy.
        if (function_exists('set_transient')) {
            set_transient('linked3_activation_errors', $data, 3600);
        }
        // Also store in option (persists longer, survives cache clears).
        if (function_exists('update_option')) {
            update_option('linked3_last_errors', $data, false);
        }
        // Also store in global for in-request access.
        $GLOBALS['linked3_early_errors'] = $errors;
        $GLOBALS['linked3_early_errors_meta'] = $data;
    }
}

// linked3_early_handler_render_batch_errors moved to extracted file

// linked3_early_handler_render_single_error moved to extracted file

if (!function_exists('linked3_early_handler_shutdown')) {
    function linked3_early_handler_shutdown()
    : void {
        $e = error_get_last();
        if (!$e) {
            return;
        }

        $fatal_types = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR,
        ];
        if (!in_array($e['type'], $fatal_types, true)) {
            return;
        }

        $type_name = linked3_early_error_type_name($e['type']);
        $message   = $e['message'];
        $file      = $e['file'];
        $line      = $e['line'];

        $trace = '';
        if (function_exists('debug_backtrace')) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
            foreach ($bt as $i => $frame) {
                $trace .= sprintf(
                    "#%d %s%s%s(%s)\n",
                    $i,
                    isset($frame['class']) ? $frame['class'] : '',
                    isset($frame['type']) ? $frame['type'] : '',
                    isset($frame['function']) ? $frame['function'] : '<unknown>',
                    isset($frame['file']) ? ' at ' . $frame['file'] . ':' . ($frame['line'] ?? '?') : ''
                );
            }
        }

        if (function_exists('error_log')) {
            error_log(sprintf('[linked3] shutdown %s: %s in %s:%d', $type_name, $message, $file, $line));
        }

        // Store in transient for activation-safe display.
        linked3_early_handler_store_errors([
            [
                'type'    => $type_name,
                'message' => $message,
                'file'    => $file,
                'line'    => $line,
                'trace'   => $trace,
            ]
        ], 'shutdown_fatal');

        // Render the error page (works for non-activation requests).
        linked3_early_handler_render_single_error($type_name, $message, $file, $line, $trace);
    }
}

if (!function_exists('linked3_early_handler_admin_notice')) {
    /**
     * Display stored errors as an admin notice. This fires on the NEXT page
     * load after activation fails (because activation output is captured by
     * ob_start/ob_end_clean and discarded).
     */
    function linked3_early_handler_admin_notice()
    : void {
        $data = null;

        // Check transient first.
        if (function_exists('get_transient')) {
            $data = get_transient('linked3_activation_errors');
            if ($data) {
                delete_transient('linked3_activation_errors');
            }
        }

        // Fallback to option.
        if (!$data && function_exists('get_option')) {
            $data = get_option('linked3_last_errors');
            if ($data) {
                delete_option('linked3_last_errors');
            }
        }

        if (empty($data) || empty($data['errors'])) {
            return;
        }

        $errors = $data['errors'];
        $count = count($errors);
        $source = isset($data['source']) ? $data['source'] : 'unknown';
        $plugin = isset($data['plugin']) ? $data['plugin'] : 'Linked3 AI';

        echo '<div class="notice notice-error" style="padding:20px;">';
        echo '<h3 style="margin-top:0;color:#d63638;">' . esc_html($plugin) . ' — ' . $count . ' Error' . ($count > 1 ? 's' : '') . ' Detected</h3>';
        echo '<p style="color:#646970;font-size:13px;margin-bottom:16px;">';
        echo 'Source: <code>' . esc_html($source) . '</code>. ';
        echo 'These errors were captured during plugin load and stored for display (because WordPress suppresses output during activation).';
        echo '</p>';

        echo '<div style="background:#fef7f7;border:1px solid #d63638;border-radius:3px;padding:16px;">';
        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #f0f0f1;">';
            echo '<div style="font-family:monospace;font-size:12px;color:#d63638;margin-bottom:4px;">';
            echo '<strong>[' . $n . ']</strong> ' . esc_html($err['type']);
            echo '</div>';
            echo '<div style="font-family:monospace;font-size:13px;color:#1d2327;margin-bottom:6px;">';
            echo esc_html($err['message']);
            echo '</div>';
            echo '<div style="font-family:monospace;font-size:12px;color:#646970;">';
            echo 'File: <strong style="color:#2271b1;">' . esc_html($err['file']) . '</strong>:' . (int) $err['line'];
            echo '</div>';
            if (!empty($err['trace'])) {
                echo '<details style="margin-top:8px;">';
                echo '<summary style="cursor:pointer;font-size:12px;color:#2271b1;">Show stack trace</summary>';
                echo '<pre style="background:#f6f7f7;padding:10px;font-size:11px;overflow-x:auto;max-height:200px;">' . esc_html($err['trace']) . '</pre>';
                echo '</details>';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<p style="margin-top:16px;font-size:13px;">';
        echo '<strong>Next steps:</strong> Fix the file(s) above, then deactivate and reactivate the plugin. ';
        echo 'Check <code>wp-content/debug.log</code> for more details.';
        echo '</p>';

        echo '</div>';
    }
}

// =============================================================================
// MAIN LOGIC — runs after all functions are defined.
// =============================================================================

// 1. Force PHP to surface every error.
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    if (defined('WP_CONTENT_DIR')) {
        @ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
    }
    error_reporting(E_ALL);
}

// 2. Disable WordPress's fatal error handler.
if (!defined('WP_FATAL_ERROR_HANDLER_ENABLED')) {
    define('WP_FATAL_ERROR_HANDLER_ENABLED', false);
}
if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) {
    define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
}
if (function_exists('add_filter')) {
    add_filter('wp_fatal_error_handler_enabled', '__return_false', 1);
}

// 3. Register admin_notices hook for activation-safe error display.
//    This fires on the NEXT page load after activation fails.
if (function_exists('add_action')) {
    add_action('admin_notices', 'linked3_early_handler_admin_notice', 1);
    add_action('network_admin_notices', 'linked3_early_handler_admin_notice', 1);
}

// 4. BATCH SYNTAX SCAN — find ALL broken PHP files before loading anything.
//    (Functions are now defined above, so this call works.)
$linked3_scan_errors = linked3_early_handler_batch_scan(
    LINKED3_EARLY_HANDLER_PLUGIN_DIR,
    ['node_modules', '.git', 'vendor', 'tests/bin', 'lib', 'assets', 'admin', 'languages', 'scripts', 'docs']
);

// 4b. BATCH SYMBOL RESOLUTION SCAN — find ALL missing class/trait/interface
//     references. This catches "Trait not found" / "Class not found" errors
//     that the syntax scan cannot detect (those are RUNTIME errors, not
//     PARSE errors). By scanning in batch we surface ALL of them at once
//     instead of dying on the first one.
$linked3_symbol_errors = linked3_early_handler_symbol_scan(
    LINKED3_EARLY_HANDLER_PLUGIN_DIR,
    ['node_modules', '.git', 'vendor', 'tests/bin', 'lib', 'assets', 'admin', 'languages', 'scripts', 'docs']
);

// Merge symbol errors into the scan errors for unified batch display.
if (!empty($linked3_symbol_errors)) {
    $linked3_scan_errors = array_merge($linked3_scan_errors, $linked3_symbol_errors);
}

if (!empty($linked3_scan_errors)) {
    // Store in transient for activation-safe display.
    linked3_early_handler_store_errors($linked3_scan_errors, 'batch_scan');

    // Render the error page and stop execution.
    // For activation requests, the output may be captured by ob, but the
    // transient will be displayed via admin_notices on the next page load.
    linked3_early_handler_render_batch_errors($linked3_scan_errors);
    exit;
}

// 5. Register shutdown handler for runtime fatals.
register_shutdown_function('linked3_early_handler_shutdown');
