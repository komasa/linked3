<?php
/**
 * AjaxNonceGuard — Centralized nonce verification middleware.
 *
 * Intercepts all wp_ajax_* hooks registered by Linked3 and verifies
 * the nonce before the handler runs. If the nonce is missing or invalid,
 * a 403 JSON error is returned and the handler is never called.
 *
 * Whitelist for endpoints that accept unauthenticated (nopriv) requests
 * or have their own custom authentication.
 *
 * @package Linked3
 * @subpackage Includes
 * @since    27.6.2
 */

declare(strict_types=1);

namespace Linked3\Includes;

if (!defined('ABSPATH')) exit;

final class AjaxNonceGuard
{
    /**
     * Nonce action string used by all Linked3 AJAX endpoints.
     */
    const NONCE_ACTION = 'linked3_ajax';

    /**
     * Endpoint whitelist: AJAX action names that should NOT be
     * guarded by the nonce check.
     *
     * - nopriv endpoints (accessible without login)
     * - endpoints with their own custom auth
     */
    private static array $whitelist = [
        // Add nopriv or custom-auth endpoint names here as needed.
        // Example: 'linked3_public_ping',
    ];

    /**
     * Capability required for all guarded AJAX endpoints.
     * Most Linked3 admin actions require manage_options.
     */
    const REQUIRED_CAP = 'manage_options';

    /**
     * Initialise the guard by hooking into admin-ajax.php.
     *
     * Must be called on 'init' priority 1 (before any add_action('wp_ajax_*')).
     * In practice we hook 'plugins_loaded' priority 0 to ensure we're
     * early enough to intercept.
     *
     * @return void
     */
    public static function init(): void
    {
        // Use 'admin_init' to ensure we're in the admin context.
        add_action('admin_init', [__CLASS__, 'guard_all_ajax'], 1);
    }

    /**
     * Intercept all wp_ajax_* requests and verify nonce + capability.
     *
     * Uses the 'wp_ajax_' hook prefix to catch all registered AJAX
     * actions. We register at priority 0 to run before any handler.
     *
     * @return void
     */
    public static function guard_all_ajax(): void
    {
        // Only act on AJAX requests.
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            return;
        }

        $action = $_REQUEST['action'] ?? '';
        if (empty($action)) {
            return;
        }

        // Skip whitelisted endpoints.
        if (in_array($action, self::$whitelist, true)) {
            return;
        }

        // Only guard linked3_* actions.
        if (strpos($action, 'linked3_') !== 0) {
            return;
        }

        // Verify nonce.
        $nonce = $_REQUEST['_ajax_nonce'] ?? $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error([
                'message' => __('Nonce verification failed. Please refresh the page and try again.', 'linked3'),
                'code'    => 'nonce_failed',
            ], 403);
        }

        // Verify capability.
        if (!current_user_can(self::REQUIRED_CAP)) {
            wp_send_json_error([
                'message' => __('Insufficient permissions.', 'linked3'),
                'code'    => 'insufficient_permissions',
            ], 403);
        }
    }

    /**
     * Generate a nonce for use in AJAX requests.
     *
     * @return string The nonce string.
     */
    public static function create_nonce(): string
    {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    /**
     * Output the nonce as a JavaScript variable for admin pages.
     *
     * @return void
     */
    public static function print_nonce_script(): void
    {
        $nonce = self::create_nonce();
        echo "<script>window.linked3AjaxNonce = '{$nonce}';</script>\n";
    }

    /**
     * Add an endpoint to the whitelist.
     *
     * @param string $action The AJAX action name (e.g. 'linked3_public_ping').
     * @return void
     */
    public static function whitelist(string $action): void
    {
        if (!in_array($action, self::$whitelist, true)) {
            self::$whitelist[] = $action;
        }
    }
}
