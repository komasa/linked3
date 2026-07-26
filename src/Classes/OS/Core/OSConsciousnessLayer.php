<?php

declare(strict_types=1);
/**
 * Linked3 Three Layer Consciousness v12.3.0
 *
 * 三层能观Badge频率标注系统
 *
 * 来源: V18道篇2.5 + 李善友2026大课三层能观模型
 *
 * 三层能观模型 (李善友):
 *   第一层 纯粹意识 → [HF]高频洞察 → 亮色
 *   第二层 逻辑意识 → [MF]中频逻辑 → 暖色
 *   第三层 时空意识 → [LF]低频信息 → 冷色
 *
 * @package Linked3\Classes\OS
 * @since 12.3.0
 * @version 12.3.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Consciousness Layer (三层意识)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/ThreeLayerConsciousness.php
 * Original class: OSConsciousnessLayer
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSConsciousnessLayer {

    /**
     * 三层能观定义
     * 来源: 李善友2026大课 三层能观模型
     */
    const CONSCIOUSNESS_LAYERS = [
        'L1' => [
            'key' => 'L1',
            'name' => __('纯粹意识', 'linked3'),
            'name_en' => 'pure_consciousness',
            'frequency' => 'HF',
            'frequency_label' => __('高频', 'linked3'),
            'color_tone' => 'bright',
            'desc' => __('灵感共振/理念/智能文明', 'linked3'),
            'maps_to' => __('洞察/结论/金句', 'linked3'),
        ],
        'L2' => [
            'key' => 'L2',
            'name' => __('逻辑意识', 'linked3'),
            'name_en' => 'logical_consciousness',
            'frequency' => 'MF',
            'frequency_label' => __('中频', 'linked3'),
            'color_tone' => 'warm',
            'desc' => __('演绎法/模型/工业文明', 'linked3'),
            'maps_to' => __('方法论/框架/步骤', 'linked3'),
        ],
        'L3' => [
            'key' => 'L3',
            'name' => __('时空意识', 'linked3'),
            'name_en' => 'spacetime_consciousness',
            'frequency' => 'LF',
            'frequency_label' => __('低频', 'linked3'),
            'color_tone' => 'cool',
            'desc' => __('归纳法/经验/农业文明', 'linked3'),
            'maps_to' => __('数据/事实/细节', 'linked3'),
        ],
    ];

    /**
     * 频率Badge定义
     */
    const FREQUENCY_BADGES = [
        'HF' => ['label' => __('高频洞察', 'linked3'), 'color' => '#FFD700', 'tone' => __('亮色', 'linked3'), 'priority' => 1],
        'MF' => ['label' => __('中频逻辑', 'linked3'), 'color' => '#FF8C42', 'tone' => __('暖色', 'linked3'), 'priority' => 2],
        'LF' => ['label' => __('低频信息', 'linked3'), 'color' => '#4682B4', 'tone' => __('冷色', 'linked3'), 'priority' => 3],
    ];

    /**
     * 色彩-频率映射
     */
    const COLOR_FREQUENCY_MAP = [
        '#FFD700' => 'HF', '#FF6B35' => 'HF', '#FF0000' => 'HF',
        '#FF8C42' => 'MF', '#D4A574' => 'MF', '#FFA500' => 'MF',
        '#4682B4' => 'LF', '#2F4F4F' => 'LF', '#1A1A1A' => 'LF',
    ];

    /**
     * 获取三层能观定义
     */
    public static function get_consciousness_layers(): array {
        return self::CONSCIOUSNESS_LAYERS;
    }

    /**
     * 为内容模块分配频率
     */
    public static function assign_frequency(string $module_type, string $content = ''): array {
        $map = [
            'insight' => 'HF', 'conclusion' => 'HF', 'golden_quote' => 'HF',
            'method' => 'MF', 'framework' => 'MF', 'steps' => 'MF',
            'data' => 'LF', 'facts' => 'LF', 'details' => 'LF',
        ];
        $freq = $map[$module_type] ?? 'MF';
        $badge = self::FREQUENCY_BADGES[$freq];
        return [
            'frequency' => $freq,
            'badge_label' => $badge['label'],
            'badge_color' => $badge['color'],
            'tone' => $badge['tone'],
        ];
    }

    /**
     * 校验频率分布
     * 规则: 全图频率应从顶部[HF]经中部[MF]到底部[LF]递进
     */
    public static function validate_frequency_distribution(array $modules): array {
        $freqs = [];
        foreach ($modules as $m) {
            $freqs[] = $m['frequency'] ?? 'MF';
        }
        $is_progressive = true;
        $order = ['HF' => 1, 'MF' => 2, 'LF' => 3];
        for ($i = 1; $i < count($freqs); $i++) {
            if (($order[$freqs[$i]] ?? 2) < ($order[$freqs[$i-1]] ?? 2)) {
                $is_progressive = false;
                break;
            }
        }
        return [
            'is_progressive' => $is_progressive,
            'frequency_sequence' => $freqs,
            'suggestion' => $is_progressive ? '分布合理' : '建议从HF→MF→LF递进',
        ];
    }

    /**
     * 构建Badge标注
     */
    public static function build_badge_annotation(string $module_id, string $freq): string {
        $badge = self::FREQUENCY_BADGES[$freq] ?? self::FREQUENCY_BADGES['MF'];
        return sprintf('[%s] %s', $freq, $badge['label']);
    }

    /**
     * 获取所有选项
     */
    public static function get_all_options(): array {
        return [
            'consciousness_layers' => self::CONSCIOUSNESS_LAYERS,
            'frequency_badges' => self::FREQUENCY_BADGES,
            'color_frequency_map' => self::COLOR_FREQUENCY_MAP,
        ];
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.3.0',
            'layers_count' => count(self::CONSCIOUSNESS_LAYERS),
            'badges_count' => count(self::FREQUENCY_BADGES),
            'source' => __('V18道篇2.5 + 李善友2026大课三层能观模型', 'linked3'),
        ];
    }
}
