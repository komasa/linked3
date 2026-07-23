<?php
/**
 * Linked3 AI — Error Renderer (extracted from early-error-handler)
 * 
 * HTML/JSON rendering for batch syntax errors.
 * Loaded by linked3-early-error-handler.php via require_once.
 *
 * @package Linked3
 */

if (!function_exists('linked3_early_handler_render_batch_errors')) {
    function linked3_early_handler_render_batch_errors($errors): void {
        $count = count($errors);
        $is_ajax = linked3_early_handler_is_ajax();

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
        echo '<title>Linked3 AI — Batch Syntax Errors</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;color:#3c434a;margin:0;padding:40px 20px;}';
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
        echo '<h1>Linked3 AI — Batch Syntax Errors</h1>';
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
        echo '</ol>';
        echo '</div>';

        echo '<div class="footer">';
        echo 'Powered by <code>linked3-early-error-handler.php v3</code> — batch syntax scan + runtime shutdown handler + activation-safe transient storage.';
        echo '</div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}

if (!function_exists('linked3_early_handler_render_single_error')) {
    function linked3_early_handler_render_single_error($type_name, $message, $file, $line, $trace): void {
        $is_ajax = linked3_early_handler_is_ajax();

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
        echo '<title>Linked3 AI — Fatal Error</title>';
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
        echo '<div class="header"><h1>Linked3 AI — Fatal Error (real error shown)</h1></div>';
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

        echo '<div class="footer">Powered by <code>linked3-early-error-handler.php v3</code></div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}
