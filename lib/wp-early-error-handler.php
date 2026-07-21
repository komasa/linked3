<?php
/**
 * WP Early Error Handler — Reusable Standalone Module
 * ================================================
 *
 * A drop-in early error handler for ANY WordPress plugin. Catches and displays
 * ALL PHP syntax errors in one batch (not just the first fatal), plus runtime
 * fatals with stack traces. Replaces WordPress's generic "There has been a
 * critical error on this website" page with a detailed, actionable error page.
 *
 * FEATURES
 * --------
 *   1. BATCH SYNTAX SCAN — Scans every .php file in your plugin directory
 *      using token_get_all($src, TOKEN_PARSE) BEFORE any file is loaded.
 *      Displays ALL broken files at once (PHP's register_shutdown_function
 *      only catches the first fatal because PHP halts at the first one).
 *   2. RUNTIME SHUTDOWN HANDLER — Catches E_ERROR, E_PARSE, E_CORE_ERROR,
 *      E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR with stack traces.
 *   3. DISABLES WP FATAL HANDLER — Turns off WordPress's generic error page
 *      so the real error is shown.
 *   4. AJAX-AWARE — Returns JSON for AJAX requests instead of HTML.
 *   5. HEADERS-SENT-SAFE — Works even if WordPress already sent headers.
 *   6. ZERO DEPENDENCIES — Pure PHP, no composer, no WP function calls
 *      required at load time (only uses add_filter if available).
 *
 * REQUIREMENTS
 * ------------
 *   - PHP 7.4+ (uses TOKEN_PARSE flag, added in PHP 7.0)
 *   - WordPress 5.2+ (for the fatal error handler filter; older WP just ignores it)
 *
 * INSTALLATION (3 steps)
 * ----------------------
 *   1. Copy this file into your plugin, e.g.:
 *        /wp-content/plugins/your-plugin/lib/wp-early-error-handler.php
 *
 *   2. At the VERY TOP of your main plugin file (right after the ABSPATH guard
 *      and the plugin header comment), add:
 *
 *        require_once __DIR__ . '/lib/wp-early-error-handler.php';
 *        wp_early_error_handler_init([
 *            'plugin_name' => 'Your Plugin Name',
 *            'plugin_dir'  => __DIR__,
 *        ]);
 *
 *   3. Done. Every page load will now:
 *        - Scan all your plugin's .php files for syntax errors.
 *        - If any are found, display a batch error page and exit.
 *        - Otherwise, register a shutdown handler for runtime fatals.
 *
 * CONFIGURATION OPTIONS
 * ---------------------
 *   Pass these keys in the array to wp_early_error_handler_init():
 *
 *     'plugin_name'    => string  Display name shown on error pages.
 *                                 Default: 'WordPress Plugin'
 *     'plugin_dir'     => string  Root directory to scan for PHP files.
 *                                 Default: dirname(__DIR__) (assumes this
 *                                 file lives in a lib/ subdirectory)
 *     'scan_on_load'   => bool    Whether to batch-scan on every load.
 *                                 Default: true (set false for production
 *                                 after you've verified all files parse)
 *     'skip_dirs'      => array   Subdirectory names to skip during scan.
 *                                 Default: ['node_modules', '.git', 'vendor',
 *                                 'tests/bin']
 *     'disable_wp_handler' => bool  Whether to disable WP's fatal handler.
 *                                   Default: true
 *     'force_display_errors' => bool  Whether to force display_errors=1.
 *                                     Default: true (only if WP_DEBUG is off)
 *
 * TROUBLESHOOTING
 * ---------------
 *   - If you see "Class not found" or "Call to undefined function" errors at
 *     runtime (not syntax errors), the batch scan won't catch them — the
 *     shutdown handler will. Check the stack trace on the error page.
 *   - If the error page itself fails to render, check that this file has no
 *     syntax errors (it's loaded before the scanner runs, so a broken
 *     handler file would crash silently).
 *   - To temporarily disable the handler, comment out the require_once line
 *     in your main plugin file.
 *
 * @package   WP_Early_Error_Handler
 * ENHANCEMENTS (Linked3 v27.6.5 fork)
 * ----------------------------------
 *   7. REQUIRE_PATH_CHECK — Validates every require_once / include path in
 *      the main plugin file BEFORE execution. Catches the #1 cause of
 *      activation fatals: PSR-4 file rename but require path not updated.
 *   8. ERROR_LOG_PERSIST — All errors written to wp-content/debug.log
 *      via error_log(), which works EVEN WHEN WordPress ob_start/ob_end_clean
 *      swallows all rendered output during activation.
 *   9. OPTION_PERSIST — Errors stored in a WP option (not transient) so
 *      they survive plugin deactivation. A standalone reader can display
 *      them without the plugin being active.
 *  10. ACTIVATION_REDIRECT — On activation failure, stores error and lets
 *      WordPress's own redirect flow show the error notice on plugins.php.
 *
 * @version   1.1.0-linked3
 * @license   MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

// Guard against double-load.
if (defined('WP_EEH_LOADED')) {
    return;
}
define('WP_EEH_LOADED', true);

define('WP_EEH_VERSION', '1.1.0-linked3');

/**
 * Initialize the early error handler.
 *
 * @param array $opts {
 *     Optional. Configuration options.
 *
 *     @type string $plugin_name         Display name shown on error pages.
 *     @type string $plugin_dir          Root directory to scan for PHP files.
 *     @type bool   $scan_on_load        Whether to batch-scan on every load.
 *     @type array  $skip_dirs           Subdirectory names to skip.
 *     @type bool   $disable_wp_handler  Whether to disable WP's fatal handler.
 *     @type bool   $force_display_errors Whether to force display_errors=1.
 * }
 */
if (!function_exists('wp_early_error_handler_init')) {
    function wp_early_error_handler_init($opts = [])
    {
        // Merge with defaults.
        $opts = array_merge([
            'plugin_name'          => 'WordPress Plugin',
            'plugin_dir'           => dirname(__DIR__),
            'main_file'            => '',          // Path to main plugin file (for require path check)
            'scan_on_load'         => true,
            'skip_dirs'            => ['node_modules', '.git', 'vendor', 'tests/bin'],
            'disable_wp_handler'   => true,
            'force_display_errors' => true,
            'check_require_paths'  => true,         // Validate require_once paths exist
            'persist_errors'       => true,         // Store errors in option + error_log
            'option_name'          => 'wp_eeh_last_errors',  // WP option key for error persistence
        ], $opts);

        // Store config in globals for the shutdown handler to access.
        $GLOBALS['wp_eeh_config'] = $opts;

        // Define constants for use in render functions.
        if (!defined('WP_EEH_PLUGIN_NAME')) {
            define('WP_EEH_PLUGIN_NAME', $opts['plugin_name']);
        }
        if (!defined('WP_EEH_PLUGIN_DIR')) {
            define('WP_EEH_PLUGIN_DIR', $opts['plugin_dir']);
        }

        // 1. Force PHP to surface every error.
        if ($opts['force_display_errors'] && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            @ini_set('display_errors', '1'); // phpcs:ignore
            @ini_set('display_startup_errors', '1'); // phpcs:ignore
            if (defined('WP_CONTENT_DIR')) {
                @ini_set('error_log', WP_CONTENT_DIR . '/debug.log'); // phpcs:ignore
            }
            error_reporting(E_ALL); // phpcs:ignore
        }

        // 2. Disable WordPress's fatal error handler.
        if ($opts['disable_wp_handler']) {
            if (!defined('WP_FATAL_ERROR_HANDLER_ENABLED')) {
                define('WP_FATAL_ERROR_HANDLER_ENABLED', false);
            }
            if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) {
                define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
            }
            if (function_exists('add_filter')) {
                add_filter('wp_fatal_error_handler_enabled', '__return_false', 1);
            }
        }

        // 3. Batch syntax scan.
        if ($opts['scan_on_load']) {
            $errors = wp_eeh_batch_scan($opts['plugin_dir'], $opts['skip_dirs']);

            // 3b. Require path validation — check every require_once/include
            // in the main plugin file points to a file that actually exists.
            // This catches the #1 activation fatal cause: PSR-4 rename left
            // stale require paths.
            if ($opts['check_require_paths'] && !empty($opts['main_file'])) {
                $path_errors = wp_eeh_check_require_paths($opts['main_file'], $opts['plugin_dir']);
                $errors = array_merge($errors, $path_errors);
            }

            if (!empty($errors)) {
                $GLOBALS['wp_eeh_errors'] = $errors;

                // Persist errors BEFORE rendering — if rendering output gets
                // swallowed by WordPress activation ob_start/ob_end_clean,
                // the errors are still in debug.log and the WP option.
                wp_eeh_persist_errors($errors, 'batch_scan', $opts);

                wp_eeh_render_batch_errors($errors, $opts['plugin_name']);
                exit;
            }
        }

        // 4. Register shutdown handler for runtime fatals.
        register_shutdown_function('wp_eeh_shutdown_handler');

        // 4b. Register admin_notices to display persisted errors on plugins.php
        //     even if the plugin itself is NOT active (via mu-plugin fallback).
        if ($opts['persist_errors'] && function_exists('add_action')) {
            add_action('admin_notices', 'wp_eeh_admin_notice_persisted_errors');
        }
    }
}

// -----------------------------------------------------------------------------
// Batch syntax scanner.
// -----------------------------------------------------------------------------
if (!function_exists('wp_eeh_batch_scan')) {
    function wp_eeh_batch_scan($plugin_dir, $skip_dirs = [])
    {
        $errors = [];

        if (!is_dir($plugin_dir)) {
            return $errors;
        }

        // Build absolute paths to skip.
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

            // Skip blacklisted directories.
            $skip = false;
            foreach ($skip_paths as $skip_path) {
                if (strpos($path, $skip_path) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $source = @file_get_contents($path);
            if ($source === false) continue;

            $rel = wp_eeh_relpath($path, $plugin_dir);

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
            $ns_line = wp_eeh_find_namespace_line($source);
            if ($ns_line !== false) {
                $bad_line = wp_eeh_find_statement_before_namespace($source, $ns_line);
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

if (!function_exists('wp_eeh_relpath')) {
    function wp_eeh_relpath($path, $base)
    {
        $rel = substr($path, strlen($base));
        $rel = ltrim($rel, '/\\');
        return $rel;
    }
}

if (!function_exists('wp_eeh_find_namespace_line')) {
    function wp_eeh_find_namespace_line($source)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], '<?php') !== false) {
                $php_started = true;
            }
            if (!$php_started) continue;
            if (preg_match('/^\s*namespace\s+[\\\\]?[\w\\\\]+\s*;/', $lines[$i])) {
                return $i;
            }
        }
        return false;
    }
}

if (!function_exists('wp_eeh_find_statement_before_namespace')) {
    function wp_eeh_find_statement_before_namespace($source, $namespace_line)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        for ($i = 0; $i < $namespace_line; $i++) {
            if (strpos($lines[$i], '<?php') !== false) {
                $php_started = true;
                continue;
            }
            if (!$php_started) continue;
            $trimmed = trim($lines[$i]);
            if ($trimmed === '') continue;
            if (strpos($trimmed, '//') === 0) continue;
            if (strpos($trimmed, '#') === 0) continue;
            if (strpos($trimmed, '/*') === 0) continue;
            if (strpos($trimmed, '*') === 0) continue;
            if (substr(rtrim($trimmed), -2) === '*/') continue;
            if (preg_match('/^declare\s*\(/', $trimmed)) continue;
            // Found a real statement before namespace.
            return $i;
        }
        return false;
    }
}

// -----------------------------------------------------------------------------
// Shutdown handler for runtime fatals.
// -----------------------------------------------------------------------------
if (!function_exists('wp_eeh_shutdown_handler')) {
    function wp_eeh_shutdown_handler()
    {
        $e = error_get_last();
        if (!$e) return;

        $fatal_types = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR,
        ];
        if (!in_array($e['type'], $fatal_types, true)) return;

        $type_name = wp_eeh_error_type_name($e['type']);
        $message   = $e['message'];
        $file      = $e['file'];
        $line      = $e['line'];

        // Build a stack trace.
        $trace = '';
        if (function_exists('debug_backtrace')) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
            foreach ($bt as $i => $frame) {
                $trace .= sprintf(
                    "#%d %s%s%s(%s)\n",
                    $i,
                    isset($frame['class']) ? $frame['class'] : '',
                    isset($frame['type']) ? $frame['type'] : '',
                    $frame['function'] ?? '<unknown>',
                    isset($frame['file']) ? ' at ' . $frame['file'] . ':' . ($frame['line'] ?? '?') : ''
                );
            }
        }

        if (function_exists('error_log')) {
            error_log(sprintf('[wp-eeh] shutdown %s: %s in %s:%d', $type_name, $message, $file, $line));
        }

        $plugin_name = $GLOBALS['wp_eeh_config']['plugin_name'] ?? 'WordPress Plugin';

        wp_eeh_render_single_error($type_name, $message, $file, $line, $trace, $plugin_name);
    }
}

// -----------------------------------------------------------------------------
// Rendering helpers.
// -----------------------------------------------------------------------------
if (!function_exists('wp_eeh_render_batch_errors')) {
    function wp_eeh_render_batch_errors($errors, $plugin_name)
    {
        $count = count($errors);
        $is_ajax = wp_eeh_is_ajax();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($is_ajax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'data'    => [
                    'error_count' => $count,
                    'errors'      => $errors,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($plugin_name) . ' — Batch Syntax Errors</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,sans-serif;background:#f0f0f1;color:#3c434a;margin:0;padding:40px 20px;}';
        echo '.wrap{max-width:1100px;margin:0 auto;}';
        echo '.header{background:#d63638;color:#fff;padding:24px 30px;border-radius:4px 4px 0 0;}';
        echo '.header h1{margin:0;font-size:22px;font-weight:600;}';
        echo '.header .count{display:inline-block;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:13px;margin-left:10px;}';
        echo '.body{background:#fff;border:1px solid #dcdcde;border-top:none;padding:24px 30px;border-radius:0 0 4px 4px;}';
        echo '.intro{color:#646970;font-size:14px;margin:0 0 20px;line-height:1.6;}';
        echo '.error-card{background:#fff;border:1px solid #dcdcde;border-left:4px solid #d63638;padding:16px 20px;margin-bottom:14px;border-radius:3px;}';
        echo '.error-card .num{display:inline-block;background:#d63638;color:#fff;width:24px;height:24px;border-radius:50%;text-align:center;line-height:24px;font-size:12px;font-weight:600;margin-right:10px;}';
        echo '.error-card .type{display:inline-block;background:#fef7f7;color:#d63638;padding:2px 8px;border-radius:3px;font-size:12px;font-family:monospace;margin-right:8px;}';
        echo '.error-card .msg{display:block;margin:8px 0 6px;font-family:monospace;font-size:13px;color:#1d2327;line-height:1.5;}';
        echo '.error-card .loc{font-family:monospace;font-size:12px;color:#646970;}';
        echo '.error-card .loc .file{color:#2271b1;}';
        echo '.steps{background:#f0f7fc;border:1px solid #c5d9ed;border-radius:3px;padding:16px 20px;margin-top:20px;}';
        echo '.steps h3{margin:0 0 10px;font-size:14px;color:#1d2327;}';
        echo '.steps ol{margin:0;padding-left:20px;color:#3c434a;font-size:13px;line-height:1.7;}';
        echo '.footer{margin-top:24px;color:#646970;font-size:12px;text-align:center;}';
        echo '.footer code{background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:11px;}';
        echo '</style></head><body>';
        echo '<div class="wrap">';
        echo '<div class="header">';
        echo '<h1>' . htmlspecialchars($plugin_name) . ' — Batch Syntax Errors</h1>';
        echo '<span class="count">' . $count . ' error' . ($count > 1 ? 's' : '') . ' found</span>';
        echo '</div>';
        echo '<div class="body">';
        echo '<p class="intro">All PHP files in the plugin were scanned before loading. '
            . 'The following file(s) have syntax errors and must be fixed. '
            . 'This page shows <strong>every</strong> broken file at once — fix them all, then reload.</p>';

        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<div class="error-card">';
            echo '<span class="num">' . $n . '</span>';
            echo '<span class="type">' . htmlspecialchars($err['type']) . '</span>';
            echo '<span class="msg">' . htmlspecialchars($err['message']) . '</span>';
            echo '<span class="loc">File: <span class="file">' . htmlspecialchars($err['file']) . '</span>:' . (int) $err['line'] . '</span>';
            echo '</div>';
        }

        echo '<div class="steps">';
        echo '<h3>Next steps</h3>';
        echo '<ol>';
        echo '<li>Open each file listed above and fix the syntax error at the indicated line.</li>';
        echo '<li>For <code>UseBeforeNamespace</code> errors: move the <code>namespace</code> declaration to be the first statement (right after <code>&lt;?php</code>), then put <code>use</code> statements after it.</li>';
        echo '<li>For <code>ParseError</code> errors: check for missing semicolons, unbalanced braces/parentheses, or typos.</li>';
        echo '<li>After fixing, reload this page — the scan runs on every request until all files pass.</li>';
        echo '<li>Check <code>wp-content/debug.log</code> for additional runtime errors.</li>';
        echo '<li>To restore the default WordPress error page, remove the early error handler require from the plugin main file.</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="footer">';
        echo 'Powered by <code>wp-early-error-handler.php v' . WP_EEH_VERSION . '</code> — batch syntax scan + runtime shutdown handler.';
        echo '</div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}

if (!function_exists('wp_eeh_render_single_error')) {
    function wp_eeh_render_single_error($type_name, $message, $file, $line, $trace, $plugin_name)
    {
        $is_ajax = wp_eeh_is_ajax();

        if ($is_ajax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'data' => [
                    'error'   => $type_name,
                    'message' => $message,
                    'file'    => $file,
                    'line'    => $line,
                    'trace'   => $trace,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($plugin_name) . ' — Fatal Error</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;color:#3c434a;margin:0;padding:40px 20px;}';
        echo '.wrap{max-width:900px;margin:0 auto;}';
        echo '.header{background:#d63638;color:#fff;padding:24px 30px;border-radius:4px 4px 0 0;}';
        echo '.header h1{margin:0;font-size:22px;font-weight:600;}';
        echo '.body{background:#fff;border:1px solid #dcdcde;border-top:none;padding:24px 30px;border-radius:0 0 4px 4px;}';
        echo '.field{margin-bottom:14px;}';
        echo '.field .label{font-weight:600;color:#1d2327;display:inline-block;width:90px;vertical-align:top;}';
        echo '.field .value{font-family:monospace;font-size:13px;color:#3c434a;display:inline-block;max-width:760px;word-break:break-all;}';
        echo '.trace{background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:14px;font-family:monospace;font-size:12px;line-height:1.6;white-space:pre-wrap;max-height:300px;overflow-y:auto;margin-top:10px;}';
        echo '.steps{background:#f0f7fc;border:1px solid #c5d9ed;border-radius:3px;padding:16px 20px;margin-top:20px;}';
        echo '.steps h3{margin:0 0 10px;font-size:14px;}';
        echo '.steps ol{margin:0;padding-left:20px;font-size:13px;line-height:1.7;color:#3c434a;}';
        echo '.footer{margin-top:24px;color:#646970;font-size:12px;text-align:center;}';
        echo '</style></head><body>';
        echo '<div class="wrap">';
        echo '<div class="header"><h1>' . htmlspecialchars($plugin_name) . ' — Fatal Error (real error shown)</h1></div>';
        echo '<div class="body">';
        echo '<p style="color:#646970;font-size:14px;margin:0 0 20px;">This page replaces the generic WordPress "critical error" page so you can see the actual cause.</p>';

        echo '<div class="field"><span class="label">Type:</span><span class="value">' . htmlspecialchars($type_name) . '</span></div>';
        echo '<div class="field"><span class="label">Message:</span><span class="value">' . htmlspecialchars($message) . '</span></div>';
        echo '<div class="field"><span class="label">File:</span><span class="value">' . htmlspecialchars($file) . ':' . (int) $line . '</span></div>';

        if (!empty($trace)) {
            echo '<div class="field"><span class="label">Stack:</span></div>';
            echo '<div class="trace">' . htmlspecialchars($trace) . '</div>';
        }

        echo '<div class="steps">';
        echo '<h3>Next steps</h3>';
        echo '<ol>';
        echo '<li>Fix the file/line above.</li>';
        echo '<li>If this is a missing-file error, the plugin zip may be incomplete — re-download and reinstall.</li>';
        echo '<li>Check <code>wp-content/debug.log</code> for the full trace.</li>';
        echo '<li>To restore the default WP error page, remove the early error handler require from the plugin main file.</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="footer">Powered by <code>wp-early-error-handler.php v' . WP_EEH_VERSION . '</code></div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}

if (!function_exists('wp_eeh_is_ajax')) {
    function wp_eeh_is_ajax()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) return true;
        if (function_exists('did_action') && did_action('wp_ajax_')) return true;
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        return false;
    }
}

if (!function_exists('wp_eeh_error_type_name')) {
    function wp_eeh_error_type_name($type)
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
        return $map[$type] ?? ('Error type #' . (int) $type);
    }
}

// -----------------------------------------------------------------------------
// Require path validator — checks require_once/include targets exist.
// This is the #1 cause of activation fatals: file renamed by PSR-4 but
// require path in main plugin file not updated.
// -----------------------------------------------------------------------------
if (!function_exists('wp_eeh_check_require_paths')) {
    function wp_eeh_check_require_paths($main_file, $plugin_dir)
    {
        $errors = [];
        if (!is_file($main_file)) {
            return $errors;
        }

        $source = @file_get_contents($main_file);
        if ($source === false) {
            return $errors;
        }

        // Remove comments to avoid false positives.
        $clean = preg_replace('!//.*$!m', '', $source);
        $clean = preg_replace('!#.*$!m', '', $clean);
        $clean = preg_replace('!/\*.*?\*/!s', '', $clean);

        // Match: require_once LINKED3_DIR . 'path'  OR  require_once __DIR__ . '/path'
        // Also match plain: require_once 'path'
        if (!preg_match_all(
            '/(?:require_once|require|include_once|include)\s*'
            . '(?:(\$\w+|\w+)\s*\.\s*)?'
            . "([\\'\"])([^\\'\"]+)\2/s",
            $clean,
            $matches,
            PREG_SET_ORDER
        )) {
            return $errors;
        }

        $main_dir = dirname($main_file);

        foreach ($matches as $m) {
            $base_var = isset($m[1]) ? $m[1] : '';
            $rel = $m[3];

            // Skip variable interpolation.
            if (strpos($rel, '$') !== false) continue;

            // Resolve base.
            if ($base_var === '__DIR__') {
                $target = $main_dir . '/' . ltrim($rel, '/');
            } else {
                // LINKED3_DIR or any other constant — resolve relative to plugin_dir.
                $target = $plugin_dir . '/' . ltrim($rel, '/');
            }

            // Normalize.
            $target = str_replace('//', '/', $target);

            if (!file_exists($target)) {
                $errors[] = [
                    'type'    => 'MissingRequirePath',
                    'message' => 'require_once target does not exist: ' . $rel,
                    'file'    => basename($main_file),
                    'line'    => substr_count(substr($source, 0, strpos($source, $m[0])), "\n") + 1,
                    'expected' => $target,
                ];
            }
        }

        return $errors;
    }
}

// -----------------------------------------------------------------------------
// Error persistence — writes to error_log AND WP option.
// This ensures errors are visible EVEN IF:
//   - WordPress activation ob_start/ob_end_clean swallows all output
//   - Plugin is deactivated after activation failure (admin_notices won't fire)
// -----------------------------------------------------------------------------
if (!function_exists('wp_eeh_persist_errors')) {
    function wp_eeh_persist_errors($errors, $source, $opts)
    {
        $data = [
            'errors'  => $errors,
            'source'  => $source,
            'plugin'  => $opts['plugin_name'] ?? 'WordPress Plugin',
            'time'    => date('Y-m-d H:i:s'),
            'url'     => ($_SERVER['REQUEST_URI'] ?? ''),
            'php_ver' => PHP_VERSION,
        ];

        // 1. error_log — ALWAYS works, even during activation ob capture.
        if (function_exists('error_log')) {
            foreach ($errors as $err) {
                $msg = sprintf(
                    '[wp-eeh][%s] %s: %s in %s:%s',
                    $source,
                    $err['type'] ?? 'Unknown',
                    $err['message'] ?? '',
                    $err['file'] ?? '',
                    $err['line'] ?? '?'
                );
                if (isset($err['expected'])) {
                    $msg .= ' (expected: ' . $err['expected'] . ')';
                }
                error_log($msg);
            }
        }

        // 2. WP option — survives plugin deactivation.
        if ($opts['persist_errors'] ?? false) {
            $option_name = $opts['option_name'] ?? 'wp_eeh_last_errors';
            if (function_exists('update_option')) {
                update_option($option_name, $data, false);
            }
        }

        // 3. Fallback: write to a file in wp-content/ if option API unavailable.
        if (defined('WP_CONTENT_DIR') && !function_exists('update_option')) {
            $log_file = WP_CONTENT_DIR . '/wp-eeh-errors.log';
            @file_put_contents($log_file, json_encode($data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        }
    }
}

// -----------------------------------------------------------------------------
// Admin notice for persisted errors — fires on admin_notices.
// Even if the plugin is deactivated, if this function was registered before
// deactivation (during the activation attempt), it will fire on the redirect.
// -----------------------------------------------------------------------------
if (!function_exists('wp_eeh_admin_notice_persisted_errors')) {
    function wp_eeh_admin_notice_persisted_errors()
    {
        $option_name = $GLOBALS['wp_eeh_config']['option_name'] ?? 'wp_eeh_last_errors';
        if (!function_exists('get_option')) return;

        $data = get_option($option_name);
        if (empty($data) || empty($data['errors'])) return;

        // Only show on plugins.php and dashboard.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !in_array($screen->base, ['plugins', 'dashboard'], true)) return;

        $errors = $data['errors'];
        $plugin = $data['plugin'] ?? 'Plugin';
        $count = count($errors);
        $time = $data['time'] ?? '';

        echo '<div class="notice notice-error" style="padding:16px 20px;">';
        echo '<p><strong>' . htmlspecialchars($plugin) . ' — Activation Error</strong> ';
        echo '<span class="dashicons dashicons-warning" style="color:#d63638;"></span> ';
        echo $count . ' error(s) detected at ' . htmlspecialchars($time) . '</p>';

        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<p style="margin:8px 0;padding:8px 12px;background:#fef7f7;border-left:3px solid #d63638;font-family:monospace;font-size:12px;">';
            echo '<strong>#' . $n . '</strong> ';
            echo '<span style="color:#d63638;">[' . htmlspecialchars($err['type'] ?? 'Error') . ']</span> ';
            echo htmlspecialchars($err['message'] ?? '');
            echo '<br>File: <code>' . htmlspecialchars($err['file'] ?? '') . ':' . htmlspecialchars($err['line'] ?? '?') . '</code>';
            if (isset($err['expected'])) {
                echo '<br>Expected: <code>' . htmlspecialchars($err['expected']) . '</code>';
            }
            echo '</p>';
        }

        echo '<p style="font-size:12px;color:#646970;">';
        echo 'Full details in <code>wp-content/debug.log</code>. ';
        echo 'Option key: <code>' . htmlspecialchars($option_name) . '</code>.';
        echo '</p>';
        echo '</div>';

        // Clear after showing so it doesn't persist forever.
        delete_option($option_name);
    }
}
