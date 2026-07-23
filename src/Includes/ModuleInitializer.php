<?php

declare(strict_types=1);
/**
 * Layer 3/3: Module Initializer.
 * Bootstraps dashboard + sub-systems that need eager init.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class ModuleInitializer
{
    /**
     * @param string $version
     * @return void
     */
    static function init(string $version): void {
        /**
         * Modules (chat, content-writer, seo, autogpt, etc.) hook here
         * to perform their own eager initialisation (register post types,
         * enqueue shared assets, schedule crons, etc.).
         */
        do_action('linked3/init', $version);
    }
}
