<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
if (!defined('ABSPATH')) exit;

/**
 * Dashboard video actions.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 */

class DashboardVideoActions extends DashboardBaseAjaxAction
{
    public static function register()
    : void {
        add_action('wp_ajax_linked3_video_generate_script', [__CLASS__, 'video_generate_script']);
        add_action('wp_ajax_linked3_video_outline', [__CLASS__, 'video_outline']);
        add_action('wp_ajax_linked3_video_segment', [__CLASS__, 'video_segment']);
    }


    /**
     * Delegate to legacy registrar method ajax_video_generate_script().
     * Action: wp_ajax_linked3_video_generate_script
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_video_generate_script().
     *             Will be migrated into this class in a future version.
     */
    public static function video_generate_script() : mixed {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_video_generate_script();
    }

    /**
     * Delegate to legacy registrar method ajax_video_outline().
     * Action: wp_ajax_linked3_video_outline
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_video_outline().
     *             Will be migrated into this class in a future version.
     */
    public static function video_outline() : mixed     {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_video_outline();
    }

    /**
     * Delegate to legacy registrar method ajax_video_segment().
     * Action: wp_ajax_linked3_video_segment
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_video_segment().
     *             Will be migrated into this class in a future version.
     */
    public static function video_segment()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_video_segment();
    }
}
