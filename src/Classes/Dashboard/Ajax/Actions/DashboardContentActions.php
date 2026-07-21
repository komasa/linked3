<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax\Actions;
use Linked3\Classes\Dashboard\Ajax\DashboardBaseAjaxAction;
use Linked3\Classes\Dashboard\DashboardMediaAjax;
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
     * Ghost method: generate_outline is handled by LongFormWriter.
     * Not registered in register(). Returns 501 if called directly.
     */
    public static function generate_outline() : mixed {
        wp_send_json_error([
            'message' => __('AJAX endpoint "ajax_generate_outline" is handled by LongFormWriter. This ghost method should not be called.', 'linked3'),
            'code' => 'ghost_method',
        ], 501);
    }

    /**
     * Ghost method: generate_section is handled by LongFormWriter.
     * Not registered in register(). Returns 501 if called directly.
     */
    public static function generate_section() : mixed     {
        wp_send_json_error([
            'message' => __('AJAX endpoint "ajax_generate_section" is handled by LongFormWriter. This ghost method should not be called.', 'linked3'),
            'code' => 'ghost_method',
        ], 501);
    }

    /**
     * Action: wp_ajax_linked3_generate_chart_prompts
     * Implementation: DashboardMediaAjax::ajax_generate_chart_prompts()
     */
    public static function generate_chart_prompts()
    {
        return DashboardMediaAjax::ajax_generate_chart_prompts();
    }
}
