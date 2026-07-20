<?php

declare(strict_types=1);
/**
 * DiagramComplexityReduction — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramComplexityReduction {
    /**
     * 象限降维: 多维信息 → 2x2矩阵。
     */
    public function quadrant(array $items, string $axisX, string $axisY): array {
        $quadrants = ['Q1' => [], 'Q2' => [], 'Q3' => [], 'Q4' => []];
        foreach ($items as $item) {
            $x = $item[$axisX] ?? 0;
            $y = $item[$axisY] ?? 0;
            if ($x >= 50 && $y >= 50) $quadrants['Q1'][] = $item;
            elseif ($x < 50 && $y >= 50) $quadrants['Q2'][] = $item;
            elseif ($x < 50 && $y < 50) $quadrants['Q3'][] = $item;
            else $quadrants['Q4'][] = $item;
        }
        return ['method' => 'quadrant', 'axis_x' => $axisX, 'axis_y' => $axisY, 'quadrants' => $quadrants];
    }

    /**
     * 漏斗降维: 多步骤 → 3层漏斗。
     */
    public function funnel(array $steps): array {
        $total = count($steps);
        $layer1 = array_slice($steps, 0, (int)($total * 0.4));
        $layer2 = array_slice($steps, (int)($total * 0.4), (int)($total * 0.35));
        $layer3 = array_slice($steps, (int)($total * 0.75));
        return [
            'method' => 'funnel',
            'layer1' => ['name' => '输入层', 'items' => $layer1, 'count' => count($layer1)],
            'layer2' => ['name' => '处理层', 'items' => $layer2, 'count' => count($layer2)],
            'layer3' => ['name' => '输出层', 'items' => $layer3, 'count' => count($layer3)],
        ];
    }

    /**
     * 聚类降维: 多项目 → N个聚类。
     */
    public function cluster(array $items, int $clusterCount = 3): array {
        // 简化: 按 key 分组
        $groups = array_chunk($items, (int)ceil(count($items) / $clusterCount));
        $result = [];
        for ($i = 0; $i < count($groups); $i++) {
            $result[] = ['name' => '聚类' . ($i + 1), 'items' => $groups[$i]];
        }
        return ['method' => 'cluster', 'clusters' => $result];
    }
}

/**
 * Linked3 Diagram Layout Engine — v6.1.0.7
 * 布局引擎: 9:16竖版/4Band/边框/徽章
 */
