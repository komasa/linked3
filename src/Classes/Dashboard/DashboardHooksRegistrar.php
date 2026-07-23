<?php

declare(strict_types=1);
/**
 * Dashboard Hooks Registrar — v4.3.9 facade.
 *
 * This class is now a thin orchestrator that delegates to two sibling
 * registrars split out of the original 951-line god class:
 *
 *   - DashboardMenuRegistrar  (menu + render + settings + sanitize + license hook)
 *   - DashboardAjaxRegistrar  (25 wp_ajax_* handlers)
 *
 * The split is internal — Hook_Manager still calls
 * `DashboardHooksRegistrar::register()` and the public contract
 * is unchanged. Existing `add_action` references (e.g. from admin views)
 * continue to resolve because the AJAX handlers moved to
 * `DashboardAjaxRegistrar` and are registered against that
 * class name — the JavaScript in admin/views still POSTs to the same
 * `wp_ajax_linked3_*` action slugs, which is all WordPress cares about.
 *
 * @package Linked3
 * @subpackage Classes\Dashboard
 */

namespace Linked3\Classes\Dashboard;

use Linked3\Classes\Addons\AddonManager;
use Linked3\Classes\Addons\IPAnonymizationAddon;
use Linked3\Classes\Addons\ConsentComplianceAddon;
use Linked3\Classes\Rest\RestController;



if (!defined('ABSPATH')) {
    exit;
}
final class DashboardHooksRegistrar
{
    /**
     * Register the Dashboard module's hooks.
     *
     * Order matters:
     *   1. REST controller (provides /wp-json/linked3/v1/* routes).
     *   2. Addons (init_all runs at priority 20 on `init`).
     *   3. Menu + settings (admin_init + admin_menu).
     *   4. AJAX handlers (wp_ajax_*).
     *
     * Each delegation is wrapped in a try/catch so a failure in one
     * registrar cannot take down the others — matching the defensive
     * style of Hook_Manager::register_hooks().
     *
     * @return void
     */
    static function register(): void {
        // 1) REST API.
        try {
            if (class_exists(RestController::class)) {
                RestController::register();
            }
        } catch (\Throwable $e) {
            self::log_failure('REST controller', $e);
        }

        // 2) Addons.
        try {
            if (class_exists(AddonManager::class)) {
                $mgr = AddonManager::instance();
                if (class_exists(IPAnonymizationAddon::class)) {
                    $mgr->register(new \Linked3\Classes\Addons\IPAnonymizationAddon());
                }
                if (class_exists(ConsentComplianceAddon::class)) {
                    $mgr->register(new \Linked3\Classes\Addons\ConsentComplianceAddon());
                }
                add_action('init', [$mgr, 'init_all'], 20);
            }
        } catch (\Throwable $e) {
            self::log_failure('Addons', $e);
        }

        // 3) Menu + settings + license hook.
        try {
            if (class_exists(DashboardMenuRegistrar::class)) {
                DashboardMenuRegistrar::register();
            }
        } catch (\Throwable $e) {
            self::log_failure('Menu registrar', $e);
        }

        // 4) AJAX handlers.
        try {
            if (class_exists(\Linked3\Classes\Dashboard\Ajax\DashboardAjaxRegistrar::class)) {
                \Linked3\Classes\Dashboard\Ajax\DashboardAjaxRegistrar::register();
            }
        } catch (\Throwable $e) {
            self::log_failure('AJAX registrar', $e);
        }
    }

    /**
     * Record a registrar-initialisation failure to the error log so the
     * site owner can diagnose it. We deliberately do NOT surface these as
     * admin notices here — Hook_Manager::show_registrar_errors() already
     * catches the top-level register() call and displays the error.
     *
     * @param string     $label Which sub-registrar failed.
     * @param \Throwable $e     The caught exception.
     * @return void
     */
    static function log_failure(string $label, \Throwable $e): void {
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[linked3] Dashboard %s register() failed: %s (in %s:%d)',
                $label,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }
}
