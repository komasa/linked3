<?php
/**
 * Global AJAX Rate Limiter (v0.1.0 hardening — C+O constitution §2).
 *
 * Token-bucket style limiter backed by WP transients. Enforces the
 * constitution-mandated 60 req/min/IP ceiling on all Linked3 AJAX
 * endpoints. Endpoints opt in via the security traits, which call
 * Rate_Limiter::gate() at the top of verify().
 *
 * Defence-in-depth: a `admin_init` watcher (registered on file load)
 * also auto-gates any AJAX action whose name contains "linked3" so a
 * handler that forgets the trait is still protected.
 *
 * @package Linked3
 * @subpackage Classes\Security
 */

namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Rate_Limiter
{
    /** Constitution §2: global 60 req/min/IP. */
    const DEFAULT_MAX_PER_MINUTE = 60;
    // Avoid const-with-WP-constant expression (MINUTE_IN_SECONDS may not be
    // defined if class is autoloaded very early). Use a method instead.
    const DEFAULT_WINDOW_SECONDS = 60;

    /** Constitution §6: per-user 100 req/hour ceiling on AI endpoints. */
    const DEFAULT_MAX_PER_USER_HOUR = 100;
    const USER_HOURLY_WINDOW_SECONDS = 3600;

    /**
     * Token-bucket gate. Call at the top of every AJAX handler.
     *
     * Fixed-window semantics: count requests in a 60s window starting
     * at first request; if count exceeds the limit, send 429 and exit;
     * when the window elapses, the bucket auto-resets.
     *
     * @param string $context 'ajax'|'rest'|'frontend' — for filter differentiation.
     * @return true Always true on success; never returns on rate-limit hit.
     */
    public static function gate($context = 'ajax')
    : bool {
        $ip  = self::client_ip();
        $max = (int) apply_filters('linked3/rate_limit_per_minute', self::DEFAULT_MAX_PER_MINUTE, $context, $ip);
        if ($max <= 0) {
            return true; // limit disabled by filter
        }

        $bucket = 'linked3_gl_' . md5($ip . '|' . $context);
        $now    = time();
        $data   = get_transient($bucket);

        if (!is_array($data) || !isset($data['count'], $data['expires'])) {
            $data = ['count' => 0, 'expires' => $now + self::DEFAULT_WINDOW_SECONDS];
        } elseif ($now > (int) $data['expires']) {
            // Window elapsed — reset.
            $data = ['count' => 0, 'expires' => $now + self::DEFAULT_WINDOW_SECONDS];
        }

        if ((int) $data['count'] >= $max) {
            self::send_429();
        }

        $data['count'] = (int) $data['count'] + 1;
        // TTL = remaining window + 1s safety margin so the transient
        // auto-expires even if the bucket is never touched again.
        $ttl = max(1, (int) $data['expires'] - $now + 1);
        set_transient($bucket, $data, $ttl);

        return true;
    }

    /**
     * Per-user hourly gate — Constitution §6: 100 req/hour per logged-in user.
     *
     * Independent of the IP-level minute gate. Guests (user_id = 0) are
     * skipped (they are bounded by the IP gate + the guest token quota in
     * Token_Manager). Returns true on allow; never returns on denial.
     *
     * @param int    $user_id
     * @param string $context  e.g. 'ai_chat', 'ai_embed'.
     * @return true
     */
    public static function per_user_hourly($user_id, $context = 'ai')
    : bool {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return true; // guests bounded by IP gate + guest quota.
        }
        $max = (int) apply_filters('linked3/rate_limit_per_user_hour', self::DEFAULT_MAX_PER_USER_HOUR, $context, $user_id);
        if ($max <= 0) {
            return true; // limit disabled by filter
        }
        $bucket = 'linked3_uh_' . $user_id . '_' . md5($context);
        $now    = time();
        $data   = get_transient($bucket);
        if (!is_array($data) || !isset($data['count'], $data['expires'])) {
            $data = ['count' => 0, 'expires' => $now + self::USER_HOURLY_WINDOW_SECONDS];
        } elseif ($now > (int) $data['expires']) {
            $data = ['count' => 0, 'expires' => $now + self::USER_HOURLY_WINDOW_SECONDS];
        }
        if ((int) $data['count'] >= $max) {
            self::send_429();
        }
        $data['count'] = (int) $data['count'] + 1;
        $ttl = max(1, (int) $data['expires'] - $now + 1);
        set_transient($bucket, $data, $ttl);
        return true;
    }

    /**
     * Defence-in-depth watcher: auto-gates any admin-ajax.php request whose
     * `action` parameter contains "linked3" — so a handler that forgets to
     * use the security trait is still bounded by the global limit.
     *
     * Hooked on `admin_init` priority 0 (runs before AJAX dispatch).
     *
     * @return void
     */
    public static function maybe_gate_linked3_ajax()
    : void {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return;
        }
        $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        if ($action === '' || stripos($action, 'linked3') === false) {
            return;
        }
        self::gate('ajax');
    }

    /**
     * Send 429 Too Many Requests and exit.
     *
     * @return never
     */
    private static function send_429()
    : void {
        if (!headers_sent()) {
            status_header(429);
            header('Retry-After: 60');
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_error(
                [
                    'code'    => 'linked3_rate_limited',
                    'message' => __('Too many requests. Please slow down and try again in a minute.', 'linked3'),
                ],
                429
            );
        }
        wp_die(
            esc_html__('请求过于频繁,请稍候。', 'linked3'),
            '',
            ['response' => 429]
        );
    }

    /**
     * Resolve client IP. Does NOT trust X-Forwarded-For by default;
     * admins behind a known proxy can opt in via the
     * `linked3/trusted_proxy` filter (also used by the frontend trait).
     *
     * @return string
     */
    public static function client_ip() : mixed {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        if (apply_filters('linked3/trusted_proxy', false)) {
            $fwd = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])) : '';
            if ($fwd) {
                $parts = explode(',', $fwd);
                $ip    = trim($parts[0]);
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
}

// Register the defence-in-depth AJAX watcher as soon as the file loads.
// This mirrors the pattern used by Linked3_Disallowed_Nopriv_Actions.
add_action('admin_init', [Linked3_Rate_Limiter::class, 'maybe_gate_linked3_ajax'], 0);
