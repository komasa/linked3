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
        1 => '生成初稿',
        2 => '校验13维',
        3 => '诊断断裂',
        4 => '修复断裂',
        5 => '再校验',
        6 => '优化密度',
        7 => '定稿归档',
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
                        $diagram['footer'] = $diagram['footer'] ?? '价值观型: 持续迭代';
                        break;
                    case 'followup_4type':
                        $diagram['followup_type'] = $diagram['followup_type'] ?? '预测型';
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
