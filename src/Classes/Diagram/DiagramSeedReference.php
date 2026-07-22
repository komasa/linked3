<?php

declare(strict_types=1);
/**
 * DiagramSeedReference — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramSeedReference {
    const MODE_ANCHOR = 'anchor';      // 锚定模式: 直接引用
    const MODE_CHAIN  = 'chain';       // 链式模式: A→B→C
    const MODE_DUAL   = 'dual';        // 双参照: 同时引用2个Seed

}

// =================================================================
// v6.4.0.4: Seed锁定3级
// =================================================================
