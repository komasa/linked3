<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Dashboard\DashboardMediaAjax;
if (!defined('ABSPATH')) exit;

/**
 * Dashboard diagram actions.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 */

class DashboardDiagramActions extends DashboardBaseAjaxAction
{
    static function register(): void {
        add_action('wp_ajax_linked3_diagram_generate', [__CLASS__, 'diagram_generate']);
        add_action('wp_ajax_linked3_diagram_validate', [__CLASS__, 'diagram_validate']);
        add_action('wp_ajax_linked3_diagram_types', [__CLASS__, 'diagram_types']);
        add_action('wp_ajax_linked3_diagram_generate_multi', [__CLASS__, 'diagram_generate_multi']);
    }


    /**
     * Action: wp_ajax_linked3_diagram_generate
     * Implementation: DashboardMediaAjax::ajax_diagram_generate()
     */
    public static function diagram_generate() : mixed {
        return DashboardMediaAjax::ajax_diagram_generate();
    }

    /**
     * Action: wp_ajax_linked3_diagram_validate
     * Implementation: DashboardMediaAjax::ajax_diagram_validate()
     */
    public static function diagram_validate() : mixed     {
        return DashboardMediaAjax::ajax_diagram_validate();
    }

    /**
     * Action: wp_ajax_linked3_diagram_types
     * Implementation: DashboardMediaAjax::ajax_diagram_types()
     */
    public static function diagram_types()
    {
        return DashboardMediaAjax::ajax_diagram_types();
    }

    /**
     * Action: wp_ajax_linked3_diagram_generate_multi
     * Implementation: DashboardMediaAjax::ajax_diagram_generate_multi()
     */
    public static function diagram_generate_multi()
    {
        return DashboardMediaAjax::ajax_diagram_generate_multi();
    }
}
