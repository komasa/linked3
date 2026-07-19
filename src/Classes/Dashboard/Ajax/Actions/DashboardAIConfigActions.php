<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
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
    public static function register()
    : void {
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
     * Delegate to legacy registrar method ajax_sync_models().
     * Action: wp_ajax_linked3_sync_models
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_sync_models().
     *             Will be migrated into this class in a future version.
     */
    public static function sync_models() : mixed {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_sync_models();
    }

    /**
     * Delegate to legacy registrar method ajax_save_ai_suffix().
     * Action: wp_ajax_linked3_save_ai_suffix
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_ai_suffix().
     *             Will be migrated into this class in a future version.
     */
    public static function save_ai_suffix() : mixed     {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_ai_suffix();
    }

    /**
     * Delegate to legacy registrar method ajax_save_advanced().
     * Action: wp_ajax_linked3_save_advanced
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_advanced().
     *             Will be migrated into this class in a future version.
     */
    public static function save_advanced()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_advanced();
    }

    /**
     * Delegate to legacy registrar method ajax_save_custom_apis().
     * Action: wp_ajax_linked3_save_custom_apis
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_custom_apis().
     *             Will be migrated into this class in a future version.
     */
    public static function save_custom_apis()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_custom_apis();
    }

    /**
     * Delegate to legacy registrar method ajax_save_provider_config().
     * Action: wp_ajax_linked3_save_provider_config
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_provider_config().
     *             Will be migrated into this class in a future version.
     */
    public static function save_provider_config()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_provider_config();
    }

    /**
     * Delegate to legacy registrar method ajax_save_seo_enhance().
     * Action: wp_ajax_linked3_save_seo_enhance
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_seo_enhance().
     *             Will be migrated into this class in a future version.
     */
    public static function save_seo_enhance()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_seo_enhance();
    }

    /**
     * Delegate to legacy registrar method ajax_save_image_settings().
     * Action: wp_ajax_linked3_save_image_settings
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_image_settings().
     *             Will be migrated into this class in a future version.
     */
    public static function save_image_settings()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_image_settings();
    }

    /**
     * Delegate to legacy registrar method ajax_test_image_station().
     * Action: wp_ajax_linked3_test_image_station
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_test_image_station().
     *             Will be migrated into this class in a future version.
     */
    public static function test_image_station()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_test_image_station();
    }

    /**
     * Delegate to legacy registrar method ajax_sync_image_models().
     * Action: wp_ajax_linked3_sync_image_models
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_sync_image_models().
     *             Will be migrated into this class in a future version.
     */
    public static function sync_image_models()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_sync_image_models();
    }

    /**
     * Delegate to legacy registrar method ajax_save_geo().
     * Action: wp_ajax_linked3_save_geo
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_geo().
     *             Will be migrated into this class in a future version.
     */
    public static function save_geo()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_geo();
    }

    /**
     * Delegate to legacy registrar method ajax_save_ai_search_keys().
     * Action: wp_ajax_linked3_save_ai_search_keys
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_save_ai_search_keys().
     *             Will be migrated into this class in a future version.
     */
    public static function save_ai_search_keys()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_save_ai_search_keys();
    }

    /**
     * Delegate to legacy registrar method ajax_regen_llms_txt().
     * Action: wp_ajax_linked3_regen_llms_txt
     *
     * @deprecated 27.1.0 This delegate exists for backward compatibility.
     *             The actual implementation lives in
     *             DashboardAjaxRegistrar::ajax_regen_llms_txt().
     *             Will be migrated into this class in a future version.
     */
    public static function regen_llms_txt()
    {
        return \Linked3\Classes\Dashboard\DashboardAjaxRegistrar::ajax_regen_llms_txt();
    }
}
