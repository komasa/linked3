<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Dashboard\DashboardConfigAjax;
if (!defined('ABSPATH')) exit;

/**
 * Dashboard aiconfig actions.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax.Actions
 * @since      27.1.0
 */

class DashboardAIConfigActions extends DashboardBaseAjaxAction
{
    static function register(): void {
        add_action('wp_ajax_linked3_sync_models', [__CLASS__, 'sync_models']);
        add_action('wp_ajax_linked3_save_ai_suffix', [__CLASS__, 'save_ai_suffix']);
        add_action('wp_ajax_linked3_save_advanced', [__CLASS__, 'save_advanced']);
        add_action('wp_ajax_linked3_save_custom_apis', [__CLASS__, 'save_custom_apis']);
        add_action('wp_ajax_linked3_save_provider_config', [__CLASS__, 'save_provider_config']);
        add_action('wp_ajax_linked3_save_seo_enhance', [__CLASS__, 'save_seo_enhance']);
        add_action('wp_ajax_linked3_save_image_settings', [__CLASS__, 'save_image_settings']);
        add_action('wp_ajax_linked3_test_image_station', [__CLASS__, 'test_image_station']);
        add_action('wp_ajax_linked3_sync_image_models', [__CLASS__, 'sync_image_models']);
        add_action('wp_ajax_linked3_save_geo', [__CLASS__, 'save_geo']);
        add_action('wp_ajax_linked3_save_ai_search_keys', [__CLASS__, 'save_ai_search_keys']);
        add_action('wp_ajax_linked3_regen_llms_txt', [__CLASS__, 'regen_llms_txt']);
    }


    /**
     * Action: wp_ajax_linked3_sync_models
     * Implementation: DashboardConfigAjax::ajax_sync_models()
     */
    public static function sync_models() : mixed {
        return DashboardConfigAjax::ajax_sync_models();
    }

    /**
     * Action: wp_ajax_linked3_save_ai_suffix
     * Implementation: DashboardConfigAjax::ajax_save_ai_suffix()
     */
    public static function save_ai_suffix() : mixed     {
        return DashboardConfigAjax::ajax_save_ai_suffix();
    }

    /**
     * Action: wp_ajax_linked3_save_advanced
     * Implementation: DashboardConfigAjax::ajax_save_advanced()
     */
    public static function save_advanced()
    {
        return DashboardConfigAjax::ajax_save_advanced();
    }

    /**
     * Action: wp_ajax_linked3_save_custom_apis
     * Implementation: DashboardConfigAjax::ajax_save_custom_apis()
     */
    public static function save_custom_apis()
    {
        return DashboardConfigAjax::ajax_save_custom_apis();
    }

    /**
     * Action: wp_ajax_linked3_save_provider_config
     * Implementation: DashboardConfigAjax::ajax_save_provider_config()
     */
    public static function save_provider_config()
    {
        return DashboardConfigAjax::ajax_save_provider_config();
    }

    /**
     * Action: wp_ajax_linked3_save_seo_enhance
     * Implementation: DashboardConfigAjax::ajax_save_seo_enhance()
     */
    public static function save_seo_enhance()
    {
        return DashboardConfigAjax::ajax_save_seo_enhance();
    }

    /**
     * Action: wp_ajax_linked3_save_image_settings
     * Implementation: DashboardConfigAjax::ajax_save_image_settings()
     */
    public static function save_image_settings()
    {
        return DashboardConfigAjax::ajax_save_image_settings();
    }

    /**
     * Action: wp_ajax_linked3_test_image_station
     * Implementation: DashboardConfigAjax::ajax_test_image_station()
     */
    public static function test_image_station()
    {
        return DashboardConfigAjax::ajax_test_image_station();
    }

    /**
     * Action: wp_ajax_linked3_sync_image_models
     * Implementation: DashboardConfigAjax::ajax_sync_image_models()
     */
    public static function sync_image_models()
    {
        return DashboardConfigAjax::ajax_sync_image_models();
    }

    /**
     * Action: wp_ajax_linked3_save_geo
     * Ghost method: never had an implementation.
     */
    public static function save_geo()
    {
        wp_send_json_error([
            'message' => __('AJAX endpoint "ajax_save_geo" is not implemented. This is a known issue from PSR-4 migration.', 'linked3'),
            'code' => 'ghost_method',
        ], 501);
    }

    /**
     * Action: wp_ajax_linked3_save_ai_search_keys
     * Implementation: DashboardConfigAjax::ajax_save_ai_search_keys()
     */
    public static function save_ai_search_keys()
    {
        return DashboardConfigAjax::ajax_save_ai_search_keys();
    }

    /**
     * Action: wp_ajax_linked3_regen_llms_txt
     * Implementation: DashboardConfigAjax::ajax_regen_llms_txt()
     */
    public static function regen_llms_txt()
    {
        return DashboardConfigAjax::ajax_regen_llms_txt();
    }
}
