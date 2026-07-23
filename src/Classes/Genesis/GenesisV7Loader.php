<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;

/**
 * GenesisV7Loader — seed library loader.
 *
 * v27.6.16-fix: loadAll() now static, loads from atom_index.json.
 */
class GenesisV7Loader
{
    public static function loadAll(): array {
        $path = __DIR__ . '/atom_index.json';
        if (!file_exists($path)) return [];
        $json = file_get_contents($path);
        if ($json === false) return [];
        return json_decode($json, true) ?: [];
    }
}
