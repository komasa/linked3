<?php

declare(strict_types=1);
/**
 * Dashboard module dependency loader.
 *
 * v4.3.9: now lists 3 registrar files (facade + menu + ajax). The main
 * Dependency_Loader's glob pass also discovers them, but keeping them
 * explicit here documents the intended load order (menu/ajax before the
 * facade that delegates to them).
 *
 * @package Linked3
 * @subpackage Classes\Dashboard
 */

namespace Linked3\Classes\Dashboard;

if (!defined('ABSPATH')) {
    exit;
}

final class DashboardDependenciesLoader
{
    public static function load()
    : void {
        $files = [
            'Classes/Dashboard/Dashboard.php',
            'Classes/Rest/RestController.php',
            'Classes/Addons/AddonManager.php',
            // v4.3.9 split: menu + ajax registrars load before the facade.
            'Classes/Dashboard/DashboardMenuRegistrar.php',
            'Classes/Dashboard/DashboardAjaxRegistrar.php',
            'Classes/Dashboard/DashboardHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
