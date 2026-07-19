<?php

declare(strict_types=1);
/**
 * Linked3 Reverse Engine v12.0.0
 *
 * 逆向8维度通用框架引擎 — 从作品/系统逆向拆解为结构化JSON
 *
 * 来源: V18 道篇2.3「逆向的逆向」+ 术篇8.2「逆向8维度通用框架」
 *
 * 核心能力:
 *   1. reverse_parse(): 接收逆向Prompt + AI返回的JSON → 解析为结构化数组
 *   2. reverse_to_seed(): 逆向结果 → 正向SEED DNA (反哺正向生产)
 *   3. reverse_validate(): 校验逆向结果完整性 (8维度+专属维度)
 *   4. reverse_compare(): 对比两个逆向结果 (用于A/B测试/竞品对比)
 *
 * 设计原理 (公理K: 独立类零继承):
 *   - 独立类, 无extends, 加载顺序安全
 *   - 所有方法为static, 无需实例化
 *   - 委托 Linked3_Reverse_Dimensions 获取维度定义
 *
 * @package Linked3\Reverse
 * @since 12.0.0
 * @version 12.0.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Reverse Engine (逆向引擎)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/ReverseEngine.php
 * Original class: Linked3_Reverse_Engine
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

if (!class_exists('\Linked3\Classes\OS\Core\Linked3_Reverse_Dimensions')) {
    require_once __DIR__ . '/OsReverseDimensions.php';
}

class OSReverseEngine {

    /**
     * 解析AI返回的逆向JSON
     *
     * @param string $json_raw AI返回的原始JSON字符串
     * @param string $engineer_type 工程师类型 (用于校验专属维度)
     * @return array|\WP_Error 解析结果或错误
     */
    public static function reverse_parse(string $json_raw, string $engineer_type = '') : mixed {
        if (empty($json_raw)) {
            return new \WP_Error('E_PARSE_EMPTY', '逆向JSON为空');
        }

        // 清理markdown代码块包裹
        $text = trim($json_raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            // 尝试提取JSON片段
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
            if (!is_array($decoded)) {
                return new \WP_Error('E_PARSE_JSON', 'JSON解析失败');
            }
        }

        // 校验8维度通用框架
        $universal = Linked3_Reverse_Dimensions::get_universal_dimensions();
        $missing = [];
        foreach ($universal as $key => $dim) {
            $field_key = $dim['name_en'];
            if (!isset($decoded[$field_key]) && !isset($decoded[$key])) {
                $missing[] = "{$key}_{$dim['name']}";
            }
        }

        if (!empty($missing)) {
            // 不阻断，但记录警告
            $decoded['_warnings'] = array_merge(
                $decoded['_warnings'] ?? [],
                ['missing_universal_dims' => $missing]
            );
        }

        // 校验专属维度
        if (!empty($engineer_type)) {
            $proprietary = Linked3_Reverse_Dimensions::get_dimensions_by_type($engineer_type);
            $missing_proprietary = [];
            foreach ($proprietary as $pkey => $pdim) {
                if (!isset($decoded[$pkey]) && !isset($decoded[$pdim['label']])) {
                    $missing_proprietary[] = $pdim['label'];
                }
            }
            if (!empty($missing_proprietary)) {
                $decoded['_warnings'] = array_merge(
                    $decoded['_warnings'] ?? [],
                    ['missing_proprietary_dims' => $missing_proprietary]
                );
            }
        }

        $decoded['_engineer_type'] = $engineer_type;
        $decoded['_parsed_at'] = current_time('mysql');

        return $decoded;
    }

    /**
     * 逆向结果 → 正向SEED DNA
     * 实现"逆向的逆向"闭环: 拆解→重建
     *
     * @param array $reverse_result 逆向解析结果
     * @return array 正向SEED DNA
     */
    public static function reverse_to_seed(array $reverse_result): array {
        $seed = [
            'character_seed' => null,
            'scene_seed' => null,
            'style_seed' => null,
            'color_palette' => null,
            'meta_tags' => [],
        ];

        // D2_角色DNA → CharacterSeed
        if (!empty($reverse_result['character_dna'])) {
            $seed['character_seed'] = self::extract_character_seed($reverse_result['character_dna']);
        }

        // D6_场景背景 → SceneSeed
        if (!empty($reverse_result['scene_background'])) {
            $seed['scene_seed'] = self::extract_scene_seed($reverse_result['scene_background']);
        }

        // D1_整体风格 + D3_色彩系统 → StyleSeed
        if (!empty($reverse_result['overall_style']) || !empty($reverse_result['color_system'])) {
            $seed['style_seed'] = self::extract_style_seed(
                $reverse_result['overall_style'] ?? '',
                $reverse_result['color_system'] ?? ''
            );
        }

        // D3_色彩系统 → ColorPalette
        if (!empty($reverse_result['color_system'])) {
            $seed['color_palette'] = self::extract_color_palette($reverse_result['color_system']);
        }

        // D8_META标签 → meta_tags
        if (!empty($reverse_result['meta_tags'])) {
            $tags = $reverse_result['meta_tags'];
            if (is_string($tags)) {
                $tags = array_filter(array_map('trim', explode(',', $tags)));
            }
            $seed['meta_tags'] = $tags;
        }

        return $seed;
    }

    /**
     * 从D2_角色DNA提取CharacterSeed
     */
    private static function extract_character_seed(string $dna_text): array {
        // 基础提取: 按斜杠分割
        $parts = array_map('trim', explode('/', $dna_text));

        return [
            'source' => 'reverse_engine',
            'raw' => $dna_text,
            'extracted_fields' => $parts,
            'gender_age' => $parts[0] ?? '',
            'hair' => $parts[1] ?? '',
            'face' => $parts[2] ?? '',
            'body' => $parts[3] ?? '',
            'costume' => $parts[4] ?? '',
            'accessory' => $parts[5] ?? '',
            'pose' => $parts[6] ?? '',
            'expression' => $parts[7] ?? '',
            'special_mark' => $parts[8] ?? '',
        ];
    }

    /**
     * 从D6_场景背景提取SceneSeed
     */
    private static function extract_scene_seed(string $scene_text): array {
        $parts = array_map('trim', explode('/', $scene_text));

        return [
            'source' => 'reverse_engine',
            'raw' => $scene_text,
            'location' => $parts[0] ?? '',
            'architecture' => $parts[1] ?? '',
            'nature' => $parts[2] ?? '',
            'props' => $parts[3] ?? '',
            'atmosphere' => $parts[4] ?? '',
            'time' => $parts[5] ?? '',
            'weather' => $parts[6] ?? '',
        ];
    }

    /**
     * 从D1+D3提取StyleSeed
     */
    private static function extract_style_seed(string $style_text, string $color_text): array {
        $style_parts = array_map('trim', explode('/', $style_text));
        $color_parts = array_map('trim', explode('/', $color_text));

        return [
            'source' => 'reverse_engine',
            'style_raw' => $style_text,
            'color_raw' => $color_text,
            'painting_school' => $style_parts[0] ?? '',
            'line_feature' => $style_parts[1] ?? '',
            'render' => $style_parts[2] ?? '',
            'texture' => $style_parts[3] ?? '',
            'mood' => $style_parts[4] ?? '',
            'primary_color' => $color_parts[0] ?? '',
            'secondary_color' => $color_parts[1] ?? '',
            'accent_color' => $color_parts[2] ?? '',
            'background_color' => $color_parts[3] ?? '',
            'lighting_type' => $color_parts[4] ?? '',
            'shadow_depth' => $color_parts[5] ?? '',
        ];
    }

    /**
     * 从D3提取ColorPalette
     */
    private static function extract_color_palette(string $color_text): array {
        $hex_pattern = '/#[0-9A-Fa-f]{6}/';
        preg_match_all($hex_pattern, $color_text, $matches);

        $colors = $matches[0] ?? [];

        return [
            'source' => 'reverse_engine',
            'raw' => $color_text,
            'hex_colors' => array_unique($colors),
            'primary' => $colors[0] ?? '',
            'secondary' => $colors[1] ?? '',
            'accent' => $colors[2] ?? '',
            'background' => $colors[3] ?? '',
        ];
    }

    /**
     * 校验逆向结果完整性
     *
     * @param array $reverse_result 逆向解析结果
     * @param string $engineer_type 工程师类型
     * @return array {score, passed, missing, feedback}
     */
    public static function reverse_validate(array $reverse_result, string $engineer_type = ''): array {
        $universal = Linked3_Reverse_Dimensions::get_universal_dimensions();
        $proprietary = !empty($engineer_type)
            ? Linked3_Reverse_Dimensions::get_dimensions_by_type($engineer_type)
            : [];

        $universal_present = 0;
        $universal_missing = [];
        foreach ($universal as $key => $dim) {
            $field_key = $dim['name_en'];
            if (isset($reverse_result[$field_key]) || isset($reverse_result[$key])) {
                $universal_present++;
            } else {
                $universal_missing[] = "{$key}_{$dim['name']}";
            }
        }

        $proprietary_present = 0;
        $proprietary_missing = [];
        foreach ($proprietary as $pkey => $pdim) {
            if (isset($reverse_result[$pkey]) || isset($reverse_result[$pdim['label']])) {
                $proprietary_present++;
            } else {
                $proprietary_missing[] = $pdim['label'];
            }
        }

        $universal_score = count($universal) > 0 ? ($universal_present / count($universal)) * 100 : 0;
        $proprietary_score = count($proprietary) > 0 ? ($proprietary_present / count($proprietary)) * 100 : 100;

        $overall_score = round($universal_score * 0.7 + $proprietary_score * 0.3, 1);
        $passed = $overall_score >= 60;

        $feedback = [];
        if (!empty($universal_missing)) {
            $feedback[] = '缺失通用维度: ' . implode(', ', $universal_missing);
        }
        if (!empty($proprietary_missing)) {
            $feedback[] = '缺失专属维度: ' . implode(', ', $proprietary_missing);
        }
        if (empty($feedback)) {
            $feedback[] = '所有维度完整';
        }

        return [
            'score' => $overall_score,
            'passed' => $passed,
            'universal_present' => $universal_present,
            'universal_total' => count($universal),
            'proprietary_present' => $proprietary_present,
            'proprietary_total' => count($proprietary),
            'missing_universal' => $universal_missing,
            'missing_proprietary' => $proprietary_missing,
            'feedback' => $feedback,
        ];
    }

    /**
     * 对比两个逆向结果 (用于A/B测试/竞品对比)
     *
     * @param array $result_a 逆向结果A
     * @param array $result_b 逆向结果B
     * @return array {similarity, differences, common_tags}
     */
    public static function reverse_compare(array $result_a, array $result_b): array {
        $universal = Linked3_Reverse_Dimensions::get_universal_dimensions();

        $similarities = [];
        $differences = [];

        foreach ($universal as $key => $dim) {
            $field_key = $dim['name_en'];
            $val_a = $result_a[$field_key] ?? ($result_a[$key] ?? '');
            $val_b = $result_b[$field_key] ?? ($result_b[$key] ?? '');

            if ($val_a === $val_b && !empty($val_a)) {
                $similarities[$dim['name']] = $val_a;
            } else {
                $differences[$dim['name']] = ['a' => $val_a, 'b' => $val_b];
            }
        }

        // 对比META标签
        $tags_a = [];
        $tags_b = [];
        if (!empty($result_a['meta_tags'])) {
            $tags_a = is_string($result_a['meta_tags'])
                ? array_filter(array_map('trim', explode(',', $result_a['meta_tags'])))
                : $result_a['meta_tags'];
        }
        if (!empty($result_b['meta_tags'])) {
            $tags_b = is_string($result_b['meta_tags'])
                ? array_filter(array_map('trim', explode(',', $result_b['meta_tags'])))
                : $result_b['meta_tags'];
        }

        $common_tags = array_intersect($tags_a, $tags_b);
        $unique_a = array_diff($tags_a, $tags_b);
        $unique_b = array_diff($tags_b, $tags_a);

        $total_dims = count($universal);
        $sim_count = count($similarities);
        $similarity_score = $total_dims > 0 ? round(($sim_count / $total_dims) * 100, 1) : 0;

        return [
            'similarity_score' => $similarity_score,
            'similarities' => $similarities,
            'differences' => $differences,
            'common_meta_tags' => array_values($common_tags),
            'unique_a_tags' => array_values($unique_a),
            'unique_b_tags' => array_values($unique_b),
        ];
    }

    /**
     * 获取引擎版本信息
     */
    public static function get_version_info(): array {
        return [
            'engine_version' => '12.0.0',
            'dimensions_version' => '12.0.0',
            'universal_dims_count' => count(Linked3_Reverse_Dimensions::get_universal_dimensions()),
            'proprietary_dims_count' => count(Linked3_Reverse_Dimensions::get_proprietary_dimensions()),
            'engineer_types_count' => count(Linked3_Reverse_Dimensions::get_engineer_types()),
        ];
    }
}
