<?php
/**
 * Dashboard AJAX — Genesis domain (v27.1.0 P10 split).
 *
 * Extracted from the 5403-line Linked3_Dashboard_Ajax_Registrar god class.
 * Owns every `wp_ajax_linked3_genesis_*` / `wp_ajax_linked3_genesis_seed_*`
 * handler so Genesis endpoints can be audited and evolved independently of
 * the rest of the Dashboard AJAX surface.
 *
 * Migration strategy (safe, incremental):
 *   Step 1 (this commit): Move only the add_action() registration calls for
 *           Genesis handlers into this class. Handler implementations stay
 *           in the legacy registrar as static methods and are referenced
 *           via forward calls. This keeps the diff small and reversible.
 *   Step 2 (next iteration): Move the handler method bodies themselves,
 *           along with the private genesis* helpers they depend on.
 *
 * @package Linked3
 * @subpackage Classes\Dashboard\Ajax
 * @since 27.1.0
 * @see Linked3_Dashboard_Ajax_Registrar  Legacy god class (to be shrunk)
 */

namespace Linked3\Classes\Dashboard\Ajax;

use Linked3\Classes\Dashboard\Linked3_Dashboard_Ajax_Registrar;



if (!defined('ABSPATH')) {
    exit;
}
/**
 * Genesis-domain AJAX controller.
 *
 * Registers 15 Genesis-related AJAX endpoints and forwards each call to
 * the corresponding static handler on the legacy registrar. Once the
 * handler bodies are migrated in step 2, the forward calls will be
 * replaced with native method implementations.
 */
final class Linked3_Dashboard_Ajax_Genesis
{
    /**
     * Register every Genesis AJAX action owned by this controller.
     *
     * Called by Linked3_Dashboard_Hooks_Registrar::register() — do not
     * call directly.
     *
     * @return void
     */
    public static function register()
    : void {
        // Genesis generation endpoints
        add_action('wp_ajax_linked3_genesis_generate',        [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_generate']);
        add_action('wp_ajax_linked3_genesis_styles',          [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_styles']);
        add_action('wp_ajax_linked3_genesis_generate_multi',  [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_generate_multi']);
        add_action('wp_ajax_linked3_genesis_test_connection', [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_test_connection']);

        // Genesis job lifecycle
        add_action('wp_ajax_linked3_genesis_start_job',  [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_start_job']);
        add_action('wp_ajax_linked3_genesis_poll_job',   [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_poll_job']);
        add_action('wp_ajax_linked3_genesis_cancel_job', [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_cancel_job']);

        // Genesis SEED management
        add_action('wp_ajax_linked3_genesis_seed_generate', [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_seed_generate']);
        add_action('wp_ajax_linked3_genesis_seed_list',     [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_seed_list']);
        add_action('wp_ajax_linked3_genesis_seed_delete',   [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_seed_delete']);
        add_action('wp_ajax_linked3_genesis_seed_export',   [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_seed_export']);

        // Genesis v9 pipeline + diagnostics
        add_action('wp_ajax_linked3_genesis_generate_v9',     [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_generate_v9']);
        add_action('wp_ajax_linked3_genesis_v9_stage1',       [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_v9_stage1']);
        add_action('wp_ajax_linked3_genesis_v9_stage2',       [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_v9_stage2']);
        add_action('wp_ajax_linked3_genesis_server_diagnostic', [Linked3_Dashboard_Ajax_Registrar::class, 'ajax_genesis_server_diagnostic']);
    }

    /**
     * @return string[] List of AJAX action names owned by this controller.
     *                  Used by the architecture test to verify the split.
     */
    public static function owned_actions(): array
    {
        return [
            'linked3_genesis_generate',
            'linked3_genesis_styles',
            'linked3_genesis_generate_multi',
            'linked3_genesis_test_connection',
            'linked3_genesis_start_job',
            'linked3_genesis_poll_job',
            'linked3_genesis_cancel_job',
            'linked3_genesis_seed_generate',
            'linked3_genesis_seed_list',
            'linked3_genesis_seed_delete',
            'linked3_genesis_seed_export',
            'linked3_genesis_generate_v9',
            'linked3_genesis_v9_stage1',
            'linked3_genesis_v9_stage2',
            'linked3_genesis_server_diagnostic',
        ];
    }
}
