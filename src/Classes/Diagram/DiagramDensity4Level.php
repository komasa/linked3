<?php

declare(strict_types=1);
/**
 * DiagramDensity4Level — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramDensity4Level {
    private array $levels = [
        'minimal' => ['name_zh' => '极简版', 'modules_per_band' => 1, 'sub_topics_per_module' => 2, 'details_per_sub' => 2, 'char_target' => 2000],
        'standard' => ['name_zh' => '标准版', 'modules_per_band' => 1, 'sub_topics_per_module' => 3, 'details_per_sub' => 2, 'char_target' => 3000],
        'deep' => ['name_zh' => '深度版', 'modules_per_band' => 2, 'sub_topics_per_module' => 3, 'details_per_sub' => 3, 'char_target' => 4000],
        'extreme' => ['name_zh' => '极致版', 'modules_per_band' => 3, 'sub_topics_per_module' => 4, 'details_per_sub' => 3, 'char_target' => 4500],
    ];

    public function getLevel(string $id): ?array {
        return $this->levels[$id] ?? null;
    }
    public function getLevels(): array { return $this->levels; }
    public function getDefault(): array { return $this->levels['deep']; }
}

// =================================================================
// v6.3.0.9: 第9维度视觉频率
// =================================================================
