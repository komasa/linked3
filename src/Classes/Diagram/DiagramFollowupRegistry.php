<?php

declare(strict_types=1);
/**
 * DiagramFollowupRegistry — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramFollowupRegistry {
    private static ?DiagramFollowupRegistry $instance = null;
    private array $followups = [];

    public static function instance(): DiagramFollowupRegistry {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register('E1', ['name_zh' => '实战型', 'name_en' => 'Practical', 'question_template' => '你属于哪种情形?立即行动', 'suitable_for' => '行动指南']);
        $this->register('E2', ['name_zh' => '决策型', 'name_en' => 'Decision', 'question_template' => 'A还是B?你的选择是', 'suitable_for' => '二选一场景']);
        $this->register('E3', ['name_zh' => '诊断型', 'name_en' => 'Diagnostic', 'question_template' => '你的症状是哪种?对号入座', 'suitable_for' => '问题诊断']);
        $this->register('E4', ['name_zh' => '预测型', 'name_en' => 'Predictive', 'question_template' => '未来3年会怎样?提前准备', 'suitable_for' => '趋势预测']);
        $this->register('E5', ['name_zh' => '追问型', 'name_en' => 'Probing', 'question_template' => '为什么?深挖底层逻辑', 'suitable_for' => '深度思考']);
        $this->register('E6', ['name_zh' => '觉察型', 'name_en' => 'Reflective', 'question_template' => '你有什么感受?共鸣', 'suitable_for' => '情感共鸣']);
    }

    public function register(string $id, array $config): void {
        $this->followups[$id] = array_merge(['id' => $id], $config);
    }
    public function get(string $id): ?array { return $this->followups[$id] ?? null; }
    public function all(): array { return $this->followups; }
}

// =================================================================
// v6.3.0.4: 4种Footer类型
// =================================================================
