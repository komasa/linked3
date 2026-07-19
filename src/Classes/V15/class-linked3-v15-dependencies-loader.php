<?php
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

final class Linked3_V15_Dependencies_Loader
{
    public static function load()
    : void {
        $files = [
            'Classes/V15/trait-linked3-v15-seed.php',
            'Classes/V15/class-linked3-v15-brand-profile-manager.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
