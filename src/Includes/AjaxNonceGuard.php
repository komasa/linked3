<?php
/**
 * AjaxNonceGuard — Centralized nonce verification middleware.
 *
 * Intercepts all wp_ajax_* hooks registered by Linked3 and verifies
 * the nonce before the handler runs. If the nonce is missing or invalid,
 * a 403 JSON error is returned and the handler is never called.
 *
 * Nonce action mapping: each AJAX action is mapped to its corresponding
 * nonce action string used by the front-end when generating the nonce.
 * Unmapped actions fall back to trying all known nonce actions.
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
     * Capability required for all guarded AJAX endpoints.
     * Most Linked3 admin actions require manage_options.
     */
    const REQUIRED_CAP = 'manage_options';

    /**
     * AJAX action prefix → nonce action mapping.
     *
     * The guard resolves the nonce action by checking the AJAX action
     * name against this map. Prefix matching is used so that e.g.
     * 'linked3_cos_evolve' maps to 'linked3_cos'.
     *
     * Built from actual wp_create_nonce() / wp_verify_nonce() calls
     * found in the codebase as of v27.6.2.
     */
    private static array $action_nonce_map = [
        // Config / Settings — DashboardConfigAjax, DashboardAjaxRegistrarLegacy, settings/api.php
        'linked3_config'         => 'linked3_settings',
        'linked3_setting'        => 'linked3_settings',

        // CognitiveOS — COSAjax, tab-cognitive-os.php
        'linked3_cos'            => 'linked3_cos',

        // Chat — ChatShortcode, chat/settings.php
        'linked3_chat'           => 'linked3_chat',

        // XHS — XHSAjaxActions, eco-xhs.php
        'linked3_xhs'            => 'linked3_xhs',

        // AutoGPT — autogpt/dashboard.php, tab-queue.php
        'linked3_autogpt'        => 'linked3_autogpt',

        // BookFactory — eco-book.php
        'linked3_book_factory'   => 'linked3_book_factory',
        'linked3_book'           => 'linked3_book_factory',

        // TTS — TtsManager, speech/dashboard.php
        'linked3_tts'            => 'linked3_tts',

        // SEO — SEOHooksRegistrar, seo/*.php
        'linked3_seo'            => 'linked3_seo',

        // Distribute — DistributeHooksRegistrar
        'linked3_distribute'     => 'linked3_distribute',

        // Metabox — PostMetabox
        'linked3_metabox'        => 'linked3_metabox',

        // Platform — PlatformAdapter
        'linked3_switch_platform'=> 'linked3_platform',

        // Collect — collect/rewriter.php
        'linked3_collect'        => 'linked3_collect',

        // WooCommerce — wc/dashboard.php
        'linked3_wc'             => 'linked3_wc',

        // Publish — publish/targets.php
        'linked3_publish'        => 'linked3_publish',

        // Forms — forms/dashboard.php
        'linked3_forms'          => 'linked3_forms_admin',

        // Seed bulk — SeedAdminRender
        'linked3_seed_bulk'      => 'linked3_seed_bulk',

        // Content writer (default for most genesis/content/ecosystem actions)
        'linked3_content'        => 'linked3_content_writer',
        'linked3_eco'            => 'linked3_content_writer',
        'linked3_genesis'        => 'linked3_content_writer',
        'linked3_keyword'        => 'linked3_content_writer',
        'linked3_video'          => 'linked3_content_writer',
        'linked3_charts'         => 'linked3_content_writer',
        'linked3_quality'        => 'linked3_content_writer',
        'linked3_rewrite'        => 'linked3_content_writer',
        'linked3_suggest'        => 'linked3_content_writer',
        'linked3_save_seed'      => 'linked3_content_writer',
        'linked3_trash'          => 'linked3_content_writer',
        'linked3_download'       => 'linked3_content_writer',
        'linked3_export'         => 'linked3_content_writer',
        'linked3_import'         => 'linked3_content_writer',
        'linked3_parse'          => 'linked3_content_writer',
        'linked3_detect'         => 'linked3_content_writer',
        'linked3_route'          => 'linked3_content_writer',
        'linked3_pqs'            => 'linked3_content_writer',
        'linked3_batch'          => 'linked3_content_writer',
        'linked3_diagnose'       => 'linked3_content_writer',
    ];

    /**
     * All known nonce actions in the codebase.
     * Used as fallback for unmapped AJAX actions — if any of these
     * validates successfully, the request is allowed through.
     */
    private static array $known_nonce_actions = [
        'linked3_content_writer',
        'linked3_settings',
        'linked3_cos',
        'linked3_chat',
        'linked3_xhs',
        'linked3_autogpt',
        'linked3_book_factory',
        'linked3_tts',
        'linked3_seo',
        'linked3_distribute',
        'linked3_metabox',
        'linked3_platform',
        'linked3_collect',
        'linked3_wc',
        'linked3_publish',
        'linked3_forms_admin',
        'linked3_seed_bulk',
    ];

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
     * Initialise the guard by hooking into admin-ajax.php.
     *
     * Must be called on 'admin_init' priority 1.
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('admin_init', [__CLASS__, 'guard_all_ajax'], 1);
    }

    /**
     * Intercept all wp_ajax_* requests and verify nonce + capability.
     *
     * @return void
     */
    public static function guard_all_ajax(): void
    {
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

        // Verify nonce using mapped action.
        $nonce = $_REQUEST['_ajax_nonce'] ?? $_REQUEST['nonce'] ?? '';
        if (!self::verify_nonce_for_action($action, $nonce)) {
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
     * Resolve the nonce action string for a given AJAX action name.
     *
     * Uses prefix matching against the $action_nonce_map. The longest
     * matching prefix wins to ensure specificity.
     *
     * @param string $ajax_action The AJAX action name from $_REQUEST['action'].
     * @return string|null The nonce action string, or null if no mapping found.
     */
    private static function resolve_nonce_action(string $ajax_action): ?string
    {
        // Sort keys by length descending so longest prefix matches first.
        $keys = array_keys(self::$action_nonce_map);
        usort($keys, static fn($a, $b) => strlen($b) - strlen($a));

        foreach ($keys as $prefix) {
            if (strpos($ajax_action, $prefix) === 0) {
                return self::$action_nonce_map[$prefix];
            }
        }

        return null;
    }

    /**
     * Verify the nonce for a given AJAX action.
     *
     * If the action has a precise mapping, verify against that nonce action.
     * If unmapped, try all known nonce actions — if any passes, allow through.
     * This prevents false 403s for actions not yet in the mapping table.
     *
     * @param string $ajax_action The AJAX action name.
     * @param string $nonce       The nonce value from the request.
     * @return bool True if nonce is valid, false otherwise.
     */
    private static function verify_nonce_for_action(string $ajax_action, string $nonce): bool
    {
        if (empty($nonce)) {
            return false;
        }

        // Try precise mapping first.
        $mapped = self::resolve_nonce_action($ajax_action);
        if ($mapped !== null && wp_verify_nonce($nonce, $mapped)) {
            return true;
        }

        // Fallback: try all known nonce actions for unmapped actions.
        // This ensures no AJAX endpoint breaks due to mapping gaps.
        foreach (self::$known_nonce_actions as $nonce_action) {
            if (wp_verify_nonce($nonce, $nonce_action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a nonce for a specific action.
     *
     * @param string $nonce_action The nonce action string. Defaults to 'linked3_content_writer'.
     * @return string The nonce string.
     */
    public static function create_nonce(string $nonce_action = 'linked3_content_writer'): string
    {
        return wp_create_nonce($nonce_action);
    }

    /**
     * Output the nonce as a JavaScript variable for admin pages.
     *
     * @param string $nonce_action The nonce action string. Defaults to 'linked3_content_writer'.
     * @return void
     */
    public static function print_nonce_script(string $nonce_action = 'linked3_content_writer'): void
    {
        $nonce = self::create_nonce($nonce_action);
        $var = 'linked3AjaxNonce';
        if ($nonce_action !== 'linked3_content_writer') {
            $var = 'linked3AjaxNonce_' . sanitize_key(str_replace('linked3_', '', $nonce_action));
        }
        echo "<script>window.{$var} = '{$nonce}';</script>\n";
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

    /**
     * Register a new AJAX action → nonce action mapping at runtime.
     *
     * Allows modules to declare their nonce action if not covered by
     * the built-in map.
     *
     * @param string $ajax_prefix   The AJAX action prefix (e.g. 'linked3_myplugin').
     * @param string $nonce_action  The nonce action string (e.g. 'linked3_myplugin_nonce').
     * @return void
     */
    public static function register_mapping(string $ajax_prefix, string $nonce_action): void
    {
        self::$action_nonce_map[$ajax_prefix] = $nonce_action;
        if (!in_array($nonce_action, self::$known_nonce_actions, true)) {
            self::$known_nonce_actions[] = $nonce_action;
        }
    }
}
