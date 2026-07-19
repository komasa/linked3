<?php

declare(strict_types=1);
/**
 * OS module — dependency loader.
 *
 * v27.0.0: Created as part of the V18 → OS module migration.
 * This is the OS module's equivalent of the V18 Dependencies_Loader.
 * It loads OS module files in dependency order during Phase 3 of the
 * main plugin's dependency loader.
 *
 * After loading all OS classes, it also loads the backward-compatibility
 * alias layer so old V18 class names continue to work.
 *
 * @package Linked3\Classes\OS
 * @since 27.0.0
 */

namespace Linked3\Classes\OS;

if (!defined('ABSPATH')) {
    exit;
}

final class OSDependenciesLoader
{
    /**
     * Load OS module files in dependency order.
     *
     * Order:
     *   1. Facade (entry point — must be first)
     *   2. Genesis Bridge (depends on Facade)
     *   3. Core modules (11 files)
     *   4. Ajax handlers (10 files)
     *   5. Admin panels (4 files)
     *   6. API/Integration modules (7 files)
     *   7. Backward-compatibility alias layer (maps old V18 class names)
     *
     * @return void
     */
    public static function load()
    : void {
        $base = LINKED3_DIR . 'src/Classes/OS/';

        // Phase 1: Facade + Bridge (must load first)
        $core_files = [
            'class-linked3-os-facade.php',
            'class-linked3-os-genesis-bridge.php',
        ];

        // Phase 2: Core modules
        $core_dir = $base . 'Core/';
        if (is_dir($core_dir)) {
            foreach (glob($core_dir . 'class-*.php') as $file) {
                $core_files[] = 'Core/' . basename($file);
            }
        }

        // Phase 3: Ajax handlers
        $ajax_dir = $base . 'Ajax/';
        if (is_dir($ajax_dir)) {
            foreach (glob($ajax_dir . 'class-*.php') as $file) {
                $core_files[] = 'Ajax/' . basename($file);
            }
        }

        // Phase 4: Admin panels
        $admin_dir = $base . 'Admin/';
        if (is_dir($admin_dir)) {
            foreach (glob($admin_dir . 'class-*.php') as $file) {
                $core_files[] = 'Admin/' . basename($file);
            }
        }

        // Phase 5: API/Integration modules
        $api_dir = $base . 'Api/';
        if (is_dir($api_dir)) {
            foreach (glob($api_dir . 'class-*.php') as $file) {
                $core_files[] = 'Api/' . basename($file);
            }
        }

        // Require all files (Facade + Bridge first, then glob results)
        foreach ($core_files as $relative) {
            $path = $base . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }

        // Phase 6: Backward-compatibility alias layer.
        // This MUST load after all OS classes are defined, so class_alias()
        // can find the target class.
        $compat_path = $base . 'class-linked3-os-compat-aliases.php';
        if (file_exists($compat_path)) {
            require_once $compat_path;
        }
    }
}
