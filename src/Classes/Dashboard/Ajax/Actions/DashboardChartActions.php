<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
if (!defined('ABSPATH')) exit;

/**
 * Dashboard chart actions.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 */

class DashboardChartActions extends DashboardBaseAjaxAction
{
    public static function register()
    : void {
        add_action('wp_ajax_linked3_chart_outline', [__CLASS__, 'chart_outline']);
        add_action('wp_ajax_linked3_chart_segment', [__CLASS__, 'chart_segment']);
    }


    /**
     * Delegate to legacy registrar method ajax_chart_outline().
     * Action: wp_ajax_linked3_chart_outline
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_chart_outline().
     *             Will be migrated into this class in a future version.
     */
    public static function chart_outline() : mixed {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_chart_outline();
    }

    /**
     * Delegate to legacy registrar method ajax_chart_segment().
     * Action: wp_ajax_linked3_chart_segment
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_chart_segment().
     *             Will be migrated into this class in a future version.
     */
    public static function chart_segment() : mixed     {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_chart_segment();
    }
}
