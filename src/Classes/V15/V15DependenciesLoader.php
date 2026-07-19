<?php

declare(strict_types=1);
/**
 * V15 module — dependency loader.
 *
 * @package Linked3
 * @subpackage Classes\V15
 */

namespace Linked3\Classes\V15;

if (!defined('ABSPATH')) {
    exit;
}

final class V15DependenciesLoader
{
    public static function load()
    : void {
        $files = [
            'Classes/V15/V15SeedTrait.php',
            'Classes/V15/V15BrandProfileManager.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
