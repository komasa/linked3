<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Dashboard\DashboardMediaAjax;
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
    public static function register(): void {
        add_action('wp_ajax_linked3_video_generate_script', [__CLASS__, 'video_generate_script']);
        add_action('wp_ajax_linked3_video_outline', [__CLASS__, 'video_outline']);
        add_action('wp_ajax_linked3_video_segment', [__CLASS__, 'video_segment']);
    }


    /**
     * Action: wp_ajax_linked3_video_generate_script
     * Implementation: DashboardMediaAjax::ajax_video_generate_script()
     */
    public static function video_generate_script() : mixed {
        return DashboardMediaAjax::ajax_video_generate_script();
    }

    /**
     * Action: wp_ajax_linked3_video_outline
     * Implementation: DashboardMediaAjax::ajax_video_outline()
     */
    public static function video_outline() : mixed     {
        return DashboardMediaAjax::ajax_video_outline();
    }

    /**
     * Action: wp_ajax_linked3_video_segment
     * Implementation: DashboardMediaAjax::ajax_video_segment()
     */
    public static function video_segment()
    {
        return DashboardMediaAjax::ajax_video_segment();
    }
}
