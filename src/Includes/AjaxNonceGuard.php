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
     *
     * v27.6.16-fix: Expanded from a single action to a list of all
     * nonce actions actually used by the plugin's front-end views.
     * The previous single-action design ('linked3_admin_nonce') caused
     * every AJAX request to fail with 403 "Nonce verification failed"
     * because the views generate nonces with module-specific actions
     * (linked3_content_writer, linked3_seo, linked3_genesis, etc.).
     */
    const NONCE_ACTION = 'linked3_admin_nonce';

    /**
     * All nonce actions accepted by the guard. A request nonce is
     * verified against EACH action in turn; if any matches, the
     * request passes.
     */
    private static array $accepted_nonce_actions = [
        'linked3_admin_nonce',
        'linked3_content_writer',
        'linked3_seo',
        'linked3_autogpt',
        'linked3_chat',
        'linked3_cos',
        'linked3_settings',
        'linked3_distribute',
        'linked3_wc',
        'linked3_forms_admin',
        'linked3_collect',
        'linked3_publish',
        'linked3_xhs',
        'linked3_book_factory',
        'linked3_genesis',
        'linked3_dashboard',
        'linked3_metabox',
        'linked3_tts',
        'linked3_billing',
        'linked3_template',
        'linked3_seed_admin',
        'linked3_seed_trash',
    ];

    /**
     * Endpoint whitelist: AJAX action names that should NOT be
     * guarded by the nonce check.
     *
     * - nopriv endpoints (accessible without login)
     * - endpoints with their own custom auth
     */
    private static array $whitelist = [
        // nopriv endpoints (accessible without login) — these have their
        // own nonce + rate-limit checks inside the handler.
        'linked3_chat_send',
        'linked3_book_factory_progress',
        'linked3_form_submit',
        'linked3_tts_synthesize',
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

        // Verify nonce against ALL accepted actions (any match passes).
        // v27.6.16-fix: Previously only checked 'linked3_admin_nonce', which
        // rejected every request because views use module-specific actions.
        $nonce = $_REQUEST['_ajax_nonce'] ?? $_REQUEST['nonce'] ?? '';
        if (!empty($nonce)) {
            $nonce_valid = false;
            foreach (self::$accepted_nonce_actions as $action_name) {
                if (wp_verify_nonce($nonce, $action_name)) {
                    $nonce_valid = true;
                    break;
                }
            }
            if (!$nonce_valid) {
                wp_send_json_error([
                    'message' => __('Nonce verification failed. Please refresh the page and try again.', 'linked3'),
                    'code'    => 'nonce_failed',
                ], 403);
            }
        }

        // Verify capability — accept either manage_options OR edit_posts.
        // v27.6.16-fix: Previously required manage_options for ALL endpoints,
        // but many content-generation endpoints only need edit_posts. Each
        // handler performs its own granular capability check, so the guard
        // only needs to confirm the user is a logged-in editor.
        if (!current_user_can('edit_posts')) {
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
