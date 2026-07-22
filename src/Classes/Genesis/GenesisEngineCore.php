<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisEngineCore — delegate wrapper for GenesisAtomIndex.
 *
 * v27.6.16-fix: All methods now properly delegate to GenesisAtomIndex::instance()
 * via static calls, fixing "Static call to instance method" errors.
 */
class GenesisEngineCore
{
    public static function searchKeyword(string $keyword): array {
        return GenesisAtomIndex::instance()->searchKeyword($keyword);
    }

    public static function getByType(string $type): array {
        return GenesisAtomIndex::instance()->getByType($type);
    }

    public static function getCharacters(): array {
        return GenesisAtomIndex::instance()->getCharacters();
    }

    public static function getScenes(): array {
        return GenesisAtomIndex::instance()->getScenes();
    }

    public static function getAtom(string $atomId): ?array {
        return GenesisAtomIndex::instance()->getAtom($atomId);
    }
}
