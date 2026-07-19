<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
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
    public static function register()
    : void {
        add_action('wp_ajax_linked3_diagram_generate', [__CLASS__, 'diagram_generate']);
        add_action('wp_ajax_linked3_diagram_validate', [__CLASS__, 'diagram_validate']);
        add_action('wp_ajax_linked3_diagram_types', [__CLASS__, 'diagram_types']);
        add_action('wp_ajax_linked3_diagram_generate_multi', [__CLASS__, 'diagram_generate_multi']);
    }


    /**
     * Delegate to legacy registrar method ajax_diagram_generate().
     * Action: wp_ajax_linked3_diagram_generate
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_diagram_generate().
     *             Will be migrated into this class in a future version.
     */
    public static function diagram_generate() : mixed {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_diagram_generate();
    }

    /**
     * Delegate to legacy registrar method ajax_diagram_validate().
     * Action: wp_ajax_linked3_diagram_validate
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_diagram_validate().
     *             Will be migrated into this class in a future version.
     */
    public static function diagram_validate() : mixed     {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_diagram_validate();
    }

    /**
     * Delegate to legacy registrar method ajax_diagram_types().
     * Action: wp_ajax_linked3_diagram_types
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_diagram_types().
     *             Will be migrated into this class in a future version.
     */
    public static function diagram_types()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_diagram_types();
    }

    /**
     * Delegate to legacy registrar method ajax_diagram_generate_multi().
     * Action: wp_ajax_linked3_diagram_generate_multi
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_diagram_generate_multi().
     *             Will be migrated into this class in a future version.
     */
    public static function diagram_generate_multi()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_diagram_generate_multi();
    }
}
