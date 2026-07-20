<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisSeedLibrary {
    private static ?GenesisSeedLibrary $instance = null;
    private array $characters = [];
    private array $scenes = [];
    private array $styles = [];
    private array $operators = [];
    private string $libDir;

        public static function instance() : mixed { return GenesisV7Extras::instance(); }

        public function __construct() { return GenesisV7Generator::__construct(); }

        public function loadAll() : mixed { return GenesisV7Extras::loadAll(); }

    public function getCharacter(string $id): ?array {
        return $this->characters[$id] ?? null;
    }
    public function getScene(string $id): ?array {
        return $this->scenes[$id] ?? null;
    }
    public function getStyle(string $id): ?array {
        return $this->styles[$id] ?? null;
    }
    public function getOperators(): array {
        return $this->operators;
    }
    public function forkCharacter(string $id, string $newId, array $overrides = []): ?array {
        $base = $this->getCharacter($id);
        if (!$base) return null;
        $forked = array_merge($base, $overrides);
        $forked['id'] = $newId;
        $forked['forked_from'] = $id;
        return $forked;
    }
}


