<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisEngineHelpers — delegate wrapper for GenesisEngineCore.
 *
 * v27.6.16-fix: All methods now static, properly delegating to GenesisEngineCore.
 */
class GenesisEngineHelpers
{
    public static function searchKeyword(string $keyword) : mixed { return GenesisEngineCore::searchKeyword($keyword); }
    public static function getByType(string $type) : mixed { return GenesisEngineCore::getByType($type); }
    public static function getCharacters() : mixed { return GenesisEngineCore::getCharacters(); }
    public static function getScenes() : mixed { return GenesisEngineCore::getScenes(); }
    public static function getTemplates() : mixed { return []; }
}
