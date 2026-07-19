<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_ProductSeed_Manager — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_ProductSeed_Manager {
    private static ?Linked3_Diagram_ProductSeed_Manager $instance = null;
    private array $seeds = [];

    public static function instance(): Linked3_Diagram_ProductSeed_Manager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function create(string $id, array $config): array {
        $seed = [
            'id' => $id,
            'material' => $config['material'] ?? '',
            'shape' => $config['shape'] ?? '',
            'glaze' => $config['glaze'] ?? '',
            'base' => $config['base'] ?? '',
            'accent' => $config['accent'] ?? '',
            'signature_light' => $config['signature_light'] ?? '',
        ];
        $this->seeds[$id] = $seed;
        return $seed;
    }

    public function get(string $id): ?array { return $this->seeds[$id] ?? null; }
    public function all(): array { return $this->seeds; }
}

// =================================================================
// v6.4.0.3: Seed引用3种模式
// =================================================================
