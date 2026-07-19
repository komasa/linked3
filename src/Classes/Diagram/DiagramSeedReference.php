<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Seed_Reference — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Seed_Reference {
    const MODE_ANCHOR = 'anchor';      // 锚定模式: 直接引用
    const MODE_CHAIN  = 'chain';       // 链式模式: A→B→C
    const MODE_DUAL   = 'dual';        // 双参照: 同时引用2个Seed

    public function reference(string $mode, string $seedId, ?string $secondSeedId = null): array {
        switch ($mode) {
            case self::MODE_ANCHOR:
                return ['mode' => $mode, 'primary' => $seedId];
            case self::MODE_CHAIN:
                return ['mode' => $mode, 'chain' => [$seedId, $secondSeedId]];
            case self::MODE_DUAL:
                return ['mode' => $mode, 'primary' => $seedId, 'secondary' => $secondSeedId];
            default:
                return ['mode' => self::MODE_ANCHOR, 'primary' => $seedId];
        }
    }
}

// =================================================================
// v6.4.0.4: Seed锁定3级
// =================================================================
