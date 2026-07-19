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
 * Original file: src/Classes/V18/Core/class-linked3-three-layer-consciousness.php
 * Original class: Linked3_Three_Layer_Consciousness
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
            'name' => '纯粹意识',
            'name_en' => 'pure_consciousness',
            'frequency' => 'HF',
            'frequency_label' => '高频',
            'color_tone' => 'bright',
            'desc' => '灵感共振/理念/智能文明',
            'maps_to' => '洞察/结论/金句',
        ],
        'L2' => [
            'key' => 'L2',
            'name' => '逻辑意识',
            'name_en' => 'logical_consciousness',
            'frequency' => 'MF',
            'frequency_label' => '中频',
            'color_tone' => 'warm',
            'desc' => '演绎法/模型/工业文明',
            'maps_to' => '方法论/框架/步骤',
        ],
        'L3' => [
            'key' => 'L3',
            'name' => '时空意识',
            'name_en' => 'spacetime_consciousness',
            'frequency' => 'LF',
            'frequency_label' => '低频',
            'color_tone' => 'cool',
            'desc' => '归纳法/经验/农业文明',
            'maps_to' => '数据/事实/细节',
        ],
    ];

    /**
     * 频率Badge定义
     */
    const FREQUENCY_BADGES = [
        'HF' => ['label' => '高频洞察', 'color' => '#FFD700', 'tone' => '亮色', 'priority' => 1],
        'MF' => ['label' => '中频逻辑', 'color' => '#FF8C42', 'tone' => '暖色', 'priority' => 2],
        'LF' => ['label' => '低频信息', 'color' => '#4682B4', 'tone' => '冷色', 'priority' => 3],
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
     * 获取频率Badge
     */
    public static function get_frequency_badges(): array {
        return self::FREQUENCY_BADGES;
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
     * 获取频率对应色彩
     */
    public static function get_color_for_frequency(string $freq): string {
        return self::FREQUENCY_BADGES[$freq]['color'] ?? '#808080';
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
            'source' => 'V18道篇2.5 + 李善友2026大课三层能观模型',
        ];
    }
}
