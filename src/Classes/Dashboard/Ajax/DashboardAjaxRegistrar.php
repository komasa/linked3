<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard\Ajax;

use Linked3\Classes\Dashboard\Ajax\Actions\DashboardTemplateActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardQueueActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardKeywordActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardAIConfigActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardContentActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardVideoActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardChartActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardDiagramActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardGenesisActions;
use Linked3\Classes\Dashboard\Ajax\Actions\DashboardGenesisV9Actions;

if (!defined('ABSPATH')) exit;

/**
 * Dashboard ajax registrar.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Dashboard.Ajax
 * @since      27.1.0
 */

final class DashboardAjaxRegistrar
{
    private static $action_classes = [
        DashboardTemplateActions::class,
        DashboardQueueActions::class,
        DashboardKeywordActions::class,
        DashboardAIConfigActions::class,
        DashboardContentActions::class,
        DashboardVideoActions::class,
        DashboardChartActions::class,
        DashboardDiagramActions::class,
        DashboardGenesisActions::class,
        DashboardGenesisV9Actions::class,
    ];

    public static function register()
    : void {
        foreach (self::$action_classes as $action_class) {
            if (class_exists($action_class) && method_exists($action_class, 'register')) {
                $action_class::register();
            }
        }
    }

}
