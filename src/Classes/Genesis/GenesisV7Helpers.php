<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisV7Helpers — delegate wrapper for GenesisV7Loader.
 *
 * v27.6.16-fix: All methods now static.
 */
class GenesisV7Helpers
{
    public static function loadAll() : mixed { return GenesisV7Loader::loadAll(); }
}
