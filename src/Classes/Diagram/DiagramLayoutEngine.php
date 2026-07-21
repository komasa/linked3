<?php

declare(strict_types=1);
/**
 * DiagramLayoutEngine — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramLayoutEngine {
    const RATIO = '9:16';
    const BAND_COUNT = 4;
    const BORDER_WIDTH = '0.75pt';
    const BORDER_RADIUS = '8px';
    const PADDING = '15%';

    private function distributeBands(array $bands): array {
        $ratios = [0.20, 0.35, 0.25, 0.20]; // Band高度占比
        $result = [];
        foreach ($bands as $i => $band) {
            $result[] = array_merge($band, [
                'band_num' => $i + 1,
                'height_ratio' => $ratios[$i] ?? 0.20,
                'y_offset' => $i > 0 ? array_sum(array_slice($ratios, 0, $i)) : 0,
            ]);
        }
        return $result;
    }
}

/**
 * Linked3 Diagram Color System — v6.1.0.8
 * 色彩系统: 9徽章色 + 全局主色 + 情绪色映射
 */
