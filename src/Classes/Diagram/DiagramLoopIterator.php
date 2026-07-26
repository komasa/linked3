<?php

declare(strict_types=1);
/**
 * DiagramLoopIterator — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramLoopIterator {
    private array $steps = [
        1 => __('生成初稿', 'linked3'),
        2 => __('校验13维', 'linked3'),
        3 => __('诊断断裂', 'linked3'),
        4 => __('修复断裂', 'linked3'),
        5 => __('再校验', 'linked3'),
        6 => __('优化密度', 'linked3'),
        7 => __('定稿归档', 'linked3'),
    ];

    private function autoFix(array $diagram, array $validation): array {
        // 自动修复: 补充缺失字段
        foreach ($validation['checks'] as $dim => $check) {
            if (!$check['passed']) {
                switch ($dim) {
                    case 'endpoint_6type':
                        $diagram['endpoint']['type'] = $diagram['endpoint']['type'] ?? 'Flywheel';
                        break;
                    case 'footer_4type':
                        $diagram['footer'] = $diagram['footer'] ?? __('价值观型: 持续迭代', 'linked3');
                        break;
                    case 'followup_4type':
                        $diagram['followup_type'] = $diagram['followup_type'] ?? __('预测型', 'linked3');
                        break;
                }
            }
        }
        return $diagram;
    }

}

// =================================================================
// v6.2.0.9: 8种断裂模式手册
// =================================================================
