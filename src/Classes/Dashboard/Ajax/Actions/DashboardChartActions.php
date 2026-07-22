<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Dashboard\DashboardMediaAjax;
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
    public static function register(): void {
        add_action('wp_ajax_linked3_chart_outline', [__CLASS__, 'chart_outline']);
        add_action('wp_ajax_linked3_chart_segment', [__CLASS__, 'chart_segment']);
    }


    /**
     * Action: wp_ajax_linked3_chart_outline
     * Implementation: DashboardMediaAjax::ajax_chart_outline()
     */
    public static function chart_outline() : mixed {
        return DashboardMediaAjax::ajax_chart_outline();
    }

    /**
     * Action: wp_ajax_linked3_chart_segment
     * Implementation: DashboardMediaAjax::ajax_chart_segment()
     */
    public static function chart_segment() : mixed     {
        return DashboardMediaAjax::ajax_chart_segment();
    }
}
