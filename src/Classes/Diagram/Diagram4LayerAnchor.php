<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_4Layer_Anchor — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_4Layer_Anchor {
    /**
     * 为每个子主题添加应用锚点。
     */
    public function addAnchors(array $subTopics): array {
        $result = [];
        foreach ($subTopics as $st) {
            $st['anchor'] = [
                'case' => $st['anchor']['case'] ?? $this->suggestCase($st['title'] ?? ''),
                'metric' => $st['anchor']['metric'] ?? $this->suggestMetric($st['title'] ?? ''),
                'action' => $st['anchor']['action'] ?? $this->suggestAction($st['title'] ?? ''),
            ];
            $result[] = $st;
        }
        return $result;
    }

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
