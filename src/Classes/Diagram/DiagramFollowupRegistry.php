<?php

declare(strict_types=1);
/**
 * DiagramFollowupRegistry — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

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
        $this->register('E1', ['name_zh' => __('实战型', 'linked3'), 'name_en' => 'Practical', 'question_template' => __('你属于哪种情形?立即行动', 'linked3'), 'suitable_for' => __('行动指南', 'linked3')]);
        $this->register('E2', ['name_zh' => __('决策型', 'linked3'), 'name_en' => 'Decision', 'question_template' => __('A还是B?你的选择是', 'linked3'), 'suitable_for' => __('二选一场景', 'linked3')]);
        $this->register('E3', ['name_zh' => __('诊断型', 'linked3'), 'name_en' => 'Diagnostic', 'question_template' => __('你的症状是哪种?对号入座', 'linked3'), 'suitable_for' => __('问题诊断', 'linked3')]);
        $this->register('E4', ['name_zh' => __('预测型', 'linked3'), 'name_en' => 'Predictive', 'question_template' => __('未来3年会怎样?提前准备', 'linked3'), 'suitable_for' => __('趋势预测', 'linked3')]);
        $this->register('E5', ['name_zh' => __('追问型', 'linked3'), 'name_en' => 'Probing', 'question_template' => __('为什么?深挖底层逻辑', 'linked3'), 'suitable_for' => __('深度思考', 'linked3')]);
        $this->register('E6', ['name_zh' => __('觉察型', 'linked3'), 'name_en' => 'Reflective', 'question_template' => __('你有什么感受?共鸣', 'linked3'), 'suitable_for' => __('情感共鸣', 'linked3')]);
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
