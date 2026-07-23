<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisEngineExtras — delegate wrapper for GenesisEngineHelpers.
 *
 * v27.6.16-fix: All methods now static, properly delegating.
 */
class GenesisEngineExtras
{
    public static function searchKeyword(string $keyword) : mixed { return GenesisEngineHelpers::searchKeyword($keyword); }
    public static function getByType(string $type) : mixed { return GenesisEngineHelpers::getByType($type); }
}
