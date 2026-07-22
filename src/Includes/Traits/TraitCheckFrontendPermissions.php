<?php

declare(strict_types=1);
/**
 * Trait: enforce logged-in user + nonce on FRONTEND AJAX handlers
 * (e.g. floating chat widget when restricted to logged-in users).
 *
 * For anonymous-allowed endpoints, use TraitCheckFrontendPermissions::verify_public()
 * which enforces nonce + IP rate-limit but NOT login.
 *
 * @package Linked3
 * @subpackage Includes\Traits
 */

namespace Linked3\Includes\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait TraitCheckFrontendPermissions
{
    /**
     * Enforce login + nonce.
     *
     * @param string $action
     * @param string $nonce_key
     * @return true
     */
    protected function verify(string $action, string $nonce_key = 'nonce'): bool {
        // Constitution §2: global 60 req/min/IP ceiling on every Linked3 AJAX.
        \Linked3\Classes\Security\RateLimiter::gate('ajax');

        if (!is_user_logged_in()) {
            $this->forbidden(__('请登录后继续。', 'linked3'));
        }
        $nonce = isset($_REQUEST[$nonce_key]) ? sanitize_text_field(wp_unslash($_REQUEST[$nonce_key])) : '';
        if (!wp_verify_nonce($nonce, $action)) {
            $this->forbidden(__('安全校验失败。', 'linked3'));
        }
        return true;
    }

    /**
     * Anonymous-allowed endpoints: nonce + IP throttle (NOT login).
     * The IP throttle is a simple transients-based bucket.
     *
     * @param string $action
     * @param string $nonce_key
     * @param int    $max_per_minute Default 30.
     * @return true
     */
    protected function verify_public(string $action, string $nonce_key = 'nonce', int $max_per_minute = 30): bool {
        // Constitution §2: global 60 req/min/IP ceiling on every Linked3 AJAX.
        \Linked3\Classes\Security\RateLimiter::gate('ajax');

        $nonce = isset($_REQUEST[$nonce_key]) ? sanitize_text_field(wp_unslash($_REQUEST[$nonce_key])) : '';
        if (!wp_verify_nonce($nonce, $action)) {
            $this->forbidden(__('安全校验失败。', 'linked3'));
        }

        // Per-action IP throttle (defence-in-depth on top of the global limiter).
        $ip = $this->client_ip();
        $bucket = 'linked3_rl_' . md5($ip . '|' . $action);
        $count = (int) get_transient($bucket);
        if ($count >= $max_per_minute) {
            wp_send_json_error(
                ['code' => 'linked3_rate_limited', 'message' => __('请求过于频繁,请稍候。', 'linked3')],
                429
            );
        }
        set_transient($bucket, $count + 1, MINUTE_IN_SECONDS);

        return true;
    }

    /**
     * Delegates to the canonical Rate_Limiter implementation so there is a
     * single source of truth for IP resolution (and the trusted-proxy filter).
     *
     * @return string
     */
    protected function client_ip(): string
    {
        return \Linked3\Classes\Security\RateLimiter::client_ip();
    }

    /**
     * @param string $message
     * @return never
     */
    protected function forbidden(string $message): void {
        wp_send_json_error(
            ['code' => 'linked3_forbidden', 'message' => $message],
            403
        );
    }
}
