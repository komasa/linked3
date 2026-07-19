<?php

declare(strict_types=1);
/**
 * Linked3 Hong Liu Flywheel v12.7.0
 *
 * 洪流公式出图飞轮量化
 *
 * 来源: V18道篇2.6 洪流公式 + 李善友2026大课洪流公式
 *
 * 洪流公式 (李善友):
 *   洪流 = 时代之势 × 人的能知 × 义无反顾的行动
 *
 * 出图飞轮映射:
 *   出图飞轮 = 模板之势 × 人的设计 × AI执行
 *
 * @package Linked3\Classes\OS
 * @since 12.7.0
 * @version 12.7.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Momentum Flywheel (洪流飞轮)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/HongLiuFlywheel.php
 * Original class: Linked3_Hong_Liu_Flywheel
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSMomentumFlywheel {

    /**
     * 飞轮3因子
     */
    const FLYWHEEL_FACTORS = [
        'momentum' => [
            'key' => 'momentum',
            'label' => '模板之势',
            'label_en' => 'momentum',
            'desc' => '时代之势/模板之势 — SEED复用率+风格引擎成熟度',
            'metrics' => ['seed_reuse_rate', 'style_engine_maturity', 'skeleton_coverage'],
            'weight' => 0.34,
        ],
        'cognition' => [
            'key' => 'cognition',
            'label' => '人的设计',
            'label_en' => 'cognition',
            'desc' => '人的能知/人的设计 — 设计师认知层级+方法论掌握度',
            'metrics' => ['designer_cognition_level', 'methodology_mastery', 'reverse_thinking_ability'],
            'weight' => 0.33,
        ],
        'action' => [
            'key' => 'action',
            'label' => 'AI执行',
            'label_en' => 'action',
            'desc' => '义无反顾的行动/AI执行 — Pipeline完成率+PQS通过率',
            'metrics' => ['pipeline_completion_rate', 'pqs_pass_rate', 'iteration_efficiency'],
            'weight' => 0.33,
        ],
    ];

    /**
     * 因子权重
     */
    const FACTOR_WEIGHTS = [
        'momentum' => 0.34,
        'cognition' => 0.33,
        'action' => 0.33,
    ];

    /**
     * 分数阈值
     */
    const SCORE_THRESHOLDS = [
        'excellent' => ['min' => 85, 'label' => '卓越', 'color' => '#2ECC71'],
        'good' => ['min' => 70, 'label' => '良好', 'color' => '#3498DB'],
        'average' => ['min' => 60, 'label' => '一般', 'color' => '#F39C12'],
        'poor' => ['min' => 0, 'label' => '待提升', 'color' => '#E74C3C'],
    ];

    /**
     * 获取飞轮3因子
     */
    public static function get_factors(): array {
        return self::FLYWHEEL_FACTORS;
    }

    /**
     * 计算飞轮分数
     * 乘法式: 3因子分数相乘后归一化到0-100
     */
    public static function calculate_flywheel_score(array $factor_scores): array {
        $momentum = max(0, min(100, $factor_scores['momentum'] ?? 0));
        $cognition = max(0, min(100, $factor_scores['cognition'] ?? 0));
        $action = max(0, min(100, $factor_scores['action'] ?? 0));

        // 乘法式: (M/100 × C/100 × A/100) ^ (1/3) × 100
        $product = ($momentum / 100) * ($cognition / 100) * ($action / 100);
        $geometric_mean = pow($product, 1/3) * 100;
        $score = round($geometric_mean, 1);

        $level = 'poor';
        $level_label = '待提升';
        $level_color = '#E74C3C';
        foreach (self::SCORE_THRESHOLDS as $key => $threshold) {
            if ($score >= $threshold['min']) {
                $level = $key;
                $level_label = $threshold['label'];
                $level_color = $threshold['color'];
                break;
            }
        }

        return [
            'total_score' => $score,
            'level' => $level,
            'level_label' => $level_label,
            'level_color' => $level_color,
            'factor_scores' => [
                'momentum' => $momentum,
                'cognition' => $cognition,
                'action' => $action,
            ],
            'formula' => 'geometric_mean(M, C, A)',
        ];
    }

    /**
     * 获取因子详情
     */
    public static function get_factor_detail(string $factor_key): array {
        return self::FLYWHEEL_FACTORS[$factor_key] ?? [];
    }

    /**
     * 建议改进方向
     */
    public static function suggest_improvement(array $factor_scores): array {
        $min_factor = '';
        $min_score = 100;
        foreach ($factor_scores as $key => $score) {
            if ($score < $min_score) {
                $min_score = $score;
                $min_factor = $key;
            }
        }
        $factor = self::FLYWHEEL_FACTORS[$min_factor] ?? [];
        return [
            'weakest_factor' => $min_factor,
            'weakest_label' => $factor['label'] ?? '',
            'weakest_score' => $min_score,
            'suggestion' => sprintf('重点提升「%s」因子，当前分数%d', $factor['label'] ?? $min_factor, $min_score),
            'metrics_to_improve' => $factor['metrics'] ?? [],
        ];
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.7.0',
            'factors_count' => count(self::FLYWHEEL_FACTORS),
            'formula' => '洪流 = 势 × 知 × 行 (乘法式)',
            'source' => 'V18道篇2.6 洪流公式 + 李善友2026大课洪流公式',
        ];
    }
}
