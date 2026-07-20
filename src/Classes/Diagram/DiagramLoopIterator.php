<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Loop_Iterator — extracted from DiagramMETALayer.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

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

    /**
     * 执行Loop迭代。
     */
    public function iterate(array $diagram, int $maxIterations = 3): array {
        $history = [];
        $current = $diagram;

        for ($iter = 1; $iter <= $maxIterations; $iter++) {
            // Step 1: 生成/使用当前版本
            // Step 2: 校验13维
            $validator = new Linked3_Diagram_Validation_13Dim();
            $validation = $validator->validate($current);

            $history[] = [
                'iteration' => $iter,
                'step' => 2,
                'score' => $validation['overall_score'],
                'passed' => $validation['passed'],
            ];

            // Step 3: 诊断断裂
            if ($validation['overall_score'] >= 90) {
                $history[] = ['iteration' => $iter, 'step' => 7, 'msg' => '定稿归档'];
                break;
            }

            // Step 4-6: 修复+优化 (简化)
            $current = $this->autoFix($current, $validation);
            $history[] = ['iteration' => $iter, 'step' => 4, 'msg' => '自动修复断裂'];
        }

        return ['final_diagram' => $current, 'iterations' => count($history), 'history' => $history];
    }

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

    public function getSteps(): array { return $this->steps; }
}

// =================================================================
// v6.2.0.9: 8种断裂模式手册
// =================================================================
