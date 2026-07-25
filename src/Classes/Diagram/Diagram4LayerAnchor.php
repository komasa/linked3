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
        return __('效果提升30%', 'linked3');
    }

    private function suggestAction(string $title): string {
        return __('立即应用', 'linked3') . $title;
    }

    public function validate(array $subTopic): array {
        $issues = [];
        if (empty($subTopic['anchor']['case'])) $issues[] = __('Case缺失', 'linked3');
        if (empty($subTopic['anchor']['metric'])) $issues[] = __('Metric缺失', 'linked3');
        if (empty($subTopic['anchor']['action'])) $issues[] = __('Action缺失', 'linked3');
        return ['passed' => empty($issues), 'issues' => $issues];
    }
}

/**
 * Linked3 Diagram Selection DecisionTree — v6.1.0.5
 * 图示选择决策树 (委托给 Type_Registry)
 */
