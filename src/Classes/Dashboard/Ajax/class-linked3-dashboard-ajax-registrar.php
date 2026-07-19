<?php
namespace Linked3\Classes\Dashboard\Ajax;

use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Template_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Queue_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Keyword_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_AIConfig_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Content_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Video_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Chart_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Diagram_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_Genesis_Actions;
use Linked3\Classes\Dashboard\Ajax\Actions\Linked3_Dashboard_GenesisV9_Actions;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard ajax registrar.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax
 * @since      27.1.0
 */

final class Linked3_Dashboard_Ajax_Registrar
{
    private static $action_classes = [
        Linked3_Dashboard_Template_Actions::class,
        Linked3_Dashboard_Queue_Actions::class,
        Linked3_Dashboard_Keyword_Actions::class,
        Linked3_Dashboard_AIConfig_Actions::class,
        Linked3_Dashboard_Content_Actions::class,
        Linked3_Dashboard_Video_Actions::class,
        Linked3_Dashboard_Chart_Actions::class,
        Linked3_Dashboard_Diagram_Actions::class,
        Linked3_Dashboard_Genesis_Actions::class,
        Linked3_Dashboard_GenesisV9_Actions::class,
    ];

    public static function register()
    : void {
        foreach (self::$action_classes as $action_class) {
            if (class_exists($action_class) && method_exists($action_class, 'register')) {
                $action_class::register();
            }
        }
    }

    public static function get_action_classes() : mixed { return self::$action_classes; }
}
