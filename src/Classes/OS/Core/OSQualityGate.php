<?php

declare(strict_types=1);
/**
 * Linked3 Reverse Quality Gate v12.9.0
 *
 * 逆向质量门禁系统
 *
 * 来源: V18验篇41.4 逆向质量门禁系统
 *
 * 5维质量拆解:
 *   Q1 完整性 — 8维度+专属维度是否齐全
 *   Q2 准确性 — 拆解结果与原对象是否匹配
 *   Q3 可复用性 — 拆解结果能否反哺正向生产
 *   Q4 一致性 — 多次拆解同一对象结果是否一致
 *   Q5 深度 — 是否挖掘到原子级meta
 *
 * @package Linked3\Reverse
 * @since 12.9.0
 * @version 12.9.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Quality Gate (质量门)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/ReverseQualityGate.php
 * Original class: OSQualityGate
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSQualityGate {

    /**
     * 5维质量定义
     */
    const QUALITY_DIMENSIONS = [
        'Q1' => [
            'key' => 'Q1',
            'name' => __('完整性', 'linked3'),
            'name_en' => 'completeness',
            'desc' => __('8维度+专属维度是否齐全', 'linked3'),
            'weight' => 0.25,
            'check_method' => __('字段计数+必填校验', 'linked3'),
        ],
        'Q2' => [
            'key' => 'Q2',
            'name' => __('准确性', 'linked3'),
            'name_en' => 'accuracy',
            'desc' => __('拆解结果与原对象是否匹配', 'linked3'),
            'weight' => 0.25,
            'check_method' => __('人工抽检+AI对比', 'linked3'),
        ],
        'Q3' => [
            'key' => 'Q3',
            'name' => __('可复用性', 'linked3'),
            'name_en' => 'reusability',
            'desc' => __('拆解结果能否反哺正向生产', 'linked3'),
            'weight' => 0.20,
            'check_method' => __('reverse_to_seed成功率', 'linked3'),
        ],
        'Q4' => [
            'key' => 'Q4',
            'name' => __('一致性', 'linked3'),
            'name_en' => 'consistency',
            'desc' => __('多次拆解同一对象结果是否一致', 'linked3'),
            'weight' => 0.15,
            'check_method' => __('3次拆解对比相似度', 'linked3'),
        ],
        'Q5' => [
            'key' => 'Q5',
            'name' => __('深度', 'linked3'),
            'name_en' => 'depth',
            'desc' => __('是否挖掘到原子级meta', 'linked3'),
            'weight' => 0.15,
            'check_method' => __('原子数+meta维度数', 'linked3'),
        ],
    ];

    /**
     * 门禁阈值
     */
    const GATE_THRESHOLDS = [
        'pass' => ['min_score' => 80, 'label' => __('通过', 'linked3'), 'color' => '#2ECC71'],
        'warn' => ['min_score' => 60, 'label' => __('告警', 'linked3'), 'color' => '#F39C12'],
        'fail' => ['min_score' => 0, 'label' => __('不通过', 'linked3'), 'color' => '#E74C3C'],
    ];

    /**
     * 维度检查项
     */
    const DIMENSION_CHECKS = [
        'Q1' => ['universal_dims_count >= 8', 'proprietary_dims_count > 0'],
        'Q2' => ['match_score >= 0.8'],
        'Q3' => ['seed_extraction_success', 'seed_field_coverage >= 0.7'],
        'Q4' => ['similarity_score >= 0.85'],
        'Q5' => ['atom_count >= 10', 'meta_dim_count >= 20'],
    ];

    /**
     * 获取门禁阈值
     */
    public static function get_gate_thresholds(): array {
        return self::GATE_THRESHOLDS;
    }

    /**
     * 生成质量报告
     */
    public static function generate_quality_report(array $gate_result): string {
        $report = __('=== 逆向质量报告 ===\n\n', 'linked3');
        $report .= sprintf("总分: %s [%s]\n", $gate_result['total_score'], $gate_result['gate_label']);
        $report .= "门禁: " . ($gate_result['passed'] ? '✓ 通过' : '✗ 不通过') . "\n\n";
        $report .= "维度得分:\n";
        foreach ($gate_result['dimension_scores'] as $key => $score) {
            $dim = self::QUALITY_DIMENSIONS[$key];
            $report .= sprintf("  %s %s: %s/100 (权重%d%%)\n", $key, $dim['name'], $score, $dim['weight']*100);
        }
        return $report;
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.9.0',
            'dimensions_count' => count(self::QUALITY_DIMENSIONS),
            'thresholds_count' => count(self::GATE_THRESHOLDS),
            'source' => __('V18验篇41.4 逆向质量门禁系统', 'linked3'),
        ];
    }
}
