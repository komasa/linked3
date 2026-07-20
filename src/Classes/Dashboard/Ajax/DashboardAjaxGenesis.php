<?php

declare(strict_types=1);
/**
 * Dashboard AJAX — Genesis domain (v27.1.0 P10 split).
 *
 * Extracted from the 5403-line DashboardAjaxRegistrar god class.
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
 * @see DashboardAjaxRegistrar  Legacy god class (to be shrunk)
 */

namespace Linked3\Classes\Dashboard\Ajax;

use Linked3\Classes\Dashboard\DashboardAjaxRegistrar;



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
final class DashboardAjaxGenesis
{
    /**
     * Register every Genesis AJAX action owned by this controller.
     *
     * Called by DashboardHooksRegistrar::register() — do not
     * call directly.
     *
     * @return void
     */
    public static function register()
    : void {
        // Genesis generation endpoints
        add_action('wp_ajax_linked3_genesis_generate',        [DashboardAjaxRegistrar::class, 'ajax_genesis_generate']);
        add_action('wp_ajax_linked3_genesis_styles',          [DashboardAjaxRegistrar::class, 'ajax_genesis_styles']);
        add_action('wp_ajax_linked3_genesis_generate_multi',  [DashboardAjaxRegistrar::class, 'ajax_genesis_generate_multi']);
        add_action('wp_ajax_linked3_genesis_test_connection', [DashboardAjaxRegistrar::class, 'ajax_genesis_test_connection']);

        // Genesis job lifecycle
        add_action('wp_ajax_linked3_genesis_start_job',  [DashboardAjaxRegistrar::class, 'ajax_genesis_start_job']);
        add_action('wp_ajax_linked3_genesis_poll_job',   [DashboardAjaxRegistrar::class, 'ajax_genesis_poll_job']);
        add_action('wp_ajax_linked3_genesis_cancel_job', [DashboardAjaxRegistrar::class, 'ajax_genesis_cancel_job']);

        // Genesis SEED management
        add_action('wp_ajax_linked3_genesis_seed_generate', [DashboardAjaxRegistrar::class, 'ajax_genesis_seed_generate']);
        add_action('wp_ajax_linked3_genesis_seed_list',     [DashboardAjaxRegistrar::class, 'ajax_genesis_seed_list']);
        add_action('wp_ajax_linked3_genesis_seed_delete',   [DashboardAjaxRegistrar::class, 'ajax_genesis_seed_delete']);
        add_action('wp_ajax_linked3_genesis_seed_export',   [DashboardAjaxRegistrar::class, 'ajax_genesis_seed_export']);

        // Genesis v9 pipeline + diagnostics
        add_action('wp_ajax_linked3_genesis_generate_v9',     [DashboardAjaxRegistrar::class, 'ajax_genesis_generate_v9']);
        add_action('wp_ajax_linked3_genesis_v9_stage1',       [DashboardAjaxRegistrar::class, 'ajax_genesis_v9_stage1']);
        add_action('wp_ajax_linked3_genesis_v9_stage2',       [DashboardAjaxRegistrar::class, 'ajax_genesis_v9_stage2']);
        add_action('wp_ajax_linked3_genesis_server_diagnostic', [DashboardAjaxRegistrar::class, 'ajax_genesis_server_diagnostic']);
    }

}
