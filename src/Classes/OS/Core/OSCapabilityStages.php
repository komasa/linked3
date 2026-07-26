<?php

declare(strict_types=1);
/**
 * Linked3 Neng Zhi Three Stages v12.8.0
 *
 * 能知三阶认知层级自动映射
 *
 * 来源: V18道篇2.5 能知三阶 + 李善友2026大课能知三阶模型
 *
 * 能知三阶 (李善友):
 *   一阶 时空意识 → 归纳法 → 农业文明 → 经验
 *   二阶 逻辑意识 → 演绎法 → 工业文明 → 模型
 *   三阶 纯粹意识 → 灵感共振 → 智能文明 → 理念
 *
 * @package Linked3\Classes\OS
 * @since 12.8.0
 * @version 12.8.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Capability Stages (能知三阶)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/NengZhiThreeStages.php
 * Original class: OSCapabilityStages
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSCapabilityStages {

    /**
     * 能知三阶定义
     */
    const THREE_STAGES = [
        'stage_1' => [
            'key' => 'stage_1',
            'label' => __('一阶·时空意识', 'linked3'),
            'label_en' => 'spacetime_consciousness',
            'cognition_method' => __('归纳法', 'linked3'),
            'civilization' => __('农业文明', 'linked3'),
            'knowledge_form' => __('经验', 'linked3'),
            'hardware' => __('眼耳鼻舌身', 'linked3'),
            'cognitive_level' => 'R',
            'level_label' => __('入门读者', 'linked3'),
        ],
        'stage_2' => [
            'key' => 'stage_2',
            'label' => __('二阶·逻辑意识', 'linked3'),
            'label_en' => 'logical_consciousness',
            'cognition_method' => __('演绎法', 'linked3'),
            'civilization' => __('工业文明', 'linked3'),
            'knowledge_form' => __('模型', 'linked3'),
            'hardware' => __('前额叶', 'linked3'),
            'cognitive_level' => 'A',
            'level_label' => __('进阶读者', 'linked3'),
        ],
        'stage_3' => [
            'key' => 'stage_3',
            'label' => __('三阶·纯粹意识', 'linked3'),
            'label_en' => 'pure_consciousness',
            'cognition_method' => __('灵感共振', 'linked3'),
            'civilization' => __('智能文明', 'linked3'),
            'knowledge_form' => __('理念', 'linked3'),
            'hardware' => __('心灵', 'linked3'),
            'cognitive_level' => 'E',
            'level_label' => __('专家读者', 'linked3'),
        ],
    ];

    /**
     * 三阶-认知层级映射
     */
    const STAGE_COGNITIVE_MAP = [
        'stage_1' => 'R',
        'stage_2' => 'A',
        'stage_3' => 'E',
    ];

    /**
     * 三阶-内容类型映射
     */
    const STAGE_CONTENT_MAP = [
        'stage_1' => ['T1法律科普', 'T4共情答疑', 'T7品牌互动'],
        'stage_2' => ['T2案例分析', 'T5避坑提示', 'T6法庭出庭'],
        'stage_3' => ['T3法律警示', 'T8深度解析'],
    ];

    /**
     * 获取能知三阶
     */
    public static function get_three_stages(): array {
        return self::THREE_STAGES;
    }

    /**
     * 映射到内容类型
     */
    public static function map_to_content_type(string $stage): array {
        return self::STAGE_CONTENT_MAP[$stage] ?? [];
    }

    /**
     * 自动检测阶段 (基于内容特征)
     */
    public static function auto_detect_stage(string $content): array {
        $stage_1_keywords = ['案例', '故事', '大白话', '入门', '基础'];
        $stage_2_keywords = ['分析', '框架', '步骤', '方法', '逻辑'];
        $stage_3_keywords = ['法理', '哲学', '本质', '理念', '终极'];

        $scores = ['stage_1' => 0, 'stage_2' => 0, 'stage_3' => 0];

        foreach ($stage_1_keywords as $kw) {
            if (mb_strpos($content, $kw) !== false) $scores['stage_1']++;
        }
        foreach ($stage_2_keywords as $kw) {
            if (mb_strpos($content, $kw) !== false) $scores['stage_2']++;
        }
        foreach ($stage_3_keywords as $kw) {
            if (mb_strpos($content, $kw) !== false) $scores['stage_3']++;
        }

        $detected = array_keys($scores, max($scores))[0];
        return [
            'detected_stage' => $detected,
            'stage_label' => self::THREE_STAGES[$detected]['label'] ?? '',
            'cognitive_level' => self::STAGE_COGNITIVE_MAP[$detected],
            'scores' => $scores,
            'confidence' => max($scores) > 0 ? 'high' : 'low',
        ];
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.8.0',
            'stages_count' => count(self::THREE_STAGES),
            'source' => __('V18道篇2.5 能知三阶 + 李善友2026大课能知三阶模型', 'linked3'),
        ];
    }
}
