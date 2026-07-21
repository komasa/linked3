<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
if (!defined('ABSPATH')) exit;

/**
 * Dashboard content actions.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 */

class DashboardContentActions extends DashboardBaseAjaxAction
{
    public static function register()
    : void {
        // generate_outline / generate_section delegates removed:
        // LongFormWriter already registers and implements these hooks.
        // The ghost delegates here would intercept and return 501.
        add_action('wp_ajax_linked3_generate_chart_prompts', [__CLASS__, 'generate_chart_prompts']);
    }


    /**
     * Delegate to legacy registrar method ajax_generate_outline().
     * Action: wp_ajax_linked3_generate_outline
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_generate_outline().
     *             Will be migrated into this class in a future version.
     */
    public static function generate_outline() : mixed {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_generate_outline();
    }

    /**
     * Delegate to legacy registrar method ajax_generate_section().
     * Action: wp_ajax_linked3_generate_section
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_generate_section().
     *             Will be migrated into this class in a future version.
     */
    public static function generate_section() : mixed     {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_generate_section();
    }

    /**
     * Delegate to legacy registrar method ajax_generate_chart_prompts().
     * Action: wp_ajax_linked3_generate_chart_prompts
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_generate_chart_prompts().
     *             Will be migrated into this class in a future version.
     */
    public static function generate_chart_prompts()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_generate_chart_prompts();
    }
}
