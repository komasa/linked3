<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisV7Extras — delegate wrapper for GenesisV7Helpers.
 *
 * v27.6.16-fix: All methods now static.
 */
class GenesisV7Extras
{
    public static function instance() : mixed { return new self(); }

    public static function loadAll() : mixed { return GenesisV7Helpers::loadAll(); }
}
