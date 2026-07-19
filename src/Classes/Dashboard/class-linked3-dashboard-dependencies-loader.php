<?php
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

final class Linked3_Dashboard_Dependencies_Loader
{
    public static function load()
    : void {
        $files = [
            'Classes/Dashboard/class-linked3-dashboard.php',
            'Classes/Rest/class-linked3-rest-controller.php',
            'Classes/Addons/class-linked3-addon-manager.php',
            // v4.3.9 split: menu + ajax registrars load before the facade.
            'Classes/Dashboard/class-linked3-dashboard-menu-registrar.php',
            'Classes/Dashboard/class-linked3-dashboard-ajax-registrar.php',
            'Classes/Dashboard/class-linked3-dashboard-hooks-registrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
