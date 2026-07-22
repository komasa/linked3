<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisSeedLibrary — delegate wrapper for GenesisV7Extras.
 *
 * v27.6.16-fix: All methods now static.
 */
class GenesisSeedLibrary {
    public static function instance() : mixed { return new self(); }

    public static function loadAll() : mixed { return GenesisV7Extras::loadAll(); }
}
