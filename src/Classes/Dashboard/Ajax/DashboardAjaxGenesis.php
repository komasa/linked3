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

use Linked3\Classes\Dashboard\GenesisProcessor;
use Linked3\Classes\Dashboard\GenesisV9Processor;



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
    static function register(): void {
        // v28.0.0: Permanently disabled — all Genesis AJAX registered by DashboardGenesisActions.
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            error_log('[linked3] DashboardAjaxGenesis::register() — dead code (v28.0.0)');
        }
        return;

    }

}
