<?php

declare(strict_types=1);
/**
 * Internationalisation. Loads .mo files from /languages.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class I18n
{
    /**
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            LINKED3_TEXT_DOMAIN,
            false,
            dirname(LINKED3_BASENAME) . '/languages/'
        );
    }
}
