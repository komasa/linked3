<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Endpoint_DecisionTree — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramEndpointDecisionTree {
    private array $rules = [
        '成长' => 'Mountain path',
        '修行' => 'Mountain path',
        '飞轮' => 'Flywheel',
        '正循环' => 'Flywheel',
        '迭代' => 'Growth spiral',
        '进化' => 'Growth spiral',
        '复利' => 'Compound curve',
        '积累' => 'Compound curve',
        '生态' => 'Ecosystem loop',
        '共生' => 'Ecosystem loop',
        '转型' => 'Transformation path',
        '蜕变' => 'Transformation path',
    ];

    public function select(string $context): array {
        foreach ($this->rules as $keyword => $typeId) {
            if (strpos($context, $keyword) !== false) {
                $ep = DiagramEndpointRegistry::instance()->get($typeId);
                return ['selected' => $typeId, 'endpoint' => $ep];
            }
        }
        $default = DiagramEndpointRegistry::instance()->get('Flywheel');
        return ['selected' => 'Flywheel', 'endpoint' => $default];
    }
}

// =================================================================
// v6.3.0.3: 6种追问类型
// =================================================================
