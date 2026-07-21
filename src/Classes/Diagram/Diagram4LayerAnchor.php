<?php

declare(strict_types=1);
/**
 * Diagram4LayerAnchor — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Diagram4LayerAnchor {
    private function suggestCase(string $title): string {
        return $title . '典型案例';
    }

    private function suggestMetric(string $title): string {
        return '效果提升30%';
    }

    private function suggestAction(string $title): string {
        return '立即应用' . $title;
    }

    public function validate(array $subTopic): array {
        $issues = [];
        if (empty($subTopic['anchor']['case'])) $issues[] = 'Case缺失';
        if (empty($subTopic['anchor']['metric'])) $issues[] = 'Metric缺失';
        if (empty($subTopic['anchor']['action'])) $issues[] = 'Action缺失';
        return ['passed' => empty($issues), 'issues' => $issues];
    }
}

/**
 * Linked3 Diagram Selection DecisionTree — v6.1.0.5
 * 图示选择决策树 (委托给 Type_Registry)
 */
