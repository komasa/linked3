<?php

declare(strict_types=1);
/**
 * Linked3 Reverse Text Creation v12.6.0
 *
 * 逆向文本创作8维度 — 10类文本T1-T8专属维度
 *
 * 来源: V18用篇28-30 逆向文本创作8维度
 *
 * @package Linked3\Reverse
 * @since 12.6.0
 * @version 12.6.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Text Creation (文本创作)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/ReverseTextCreation.php
 * Original class: OSTextCreation
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSTextCreation {

    /**
     * 10类文本类型
     */
    const TEXT_TYPES = [
        'novel' => __('小说创作', 'linked3'),
        'poetry' => __('诗歌创作', 'linked3'),
        'ad' => __('广告创作', 'linked3'),
        'tech_doc' => __('技术文档', 'linked3'),
        'news' => __('新闻创作', 'linked3'),
        'academic' => __('学术论文', 'linked3'),
        'copywriting' => __('文案', 'linked3'),
        'screenplay' => __('剧本', 'linked3'),
        'comic_strip' => __('条漫', 'linked3'),
        'short_drama' => __('短剧', 'linked3'),
    ];

    /**
     * T1-T8文本专属8维度
     */
    const TEXT_DIMENSIONS = [
        'T1' => ['key' => 'T1', 'name' => __('题材', 'linked3'), 'fields' => __('类型/领域/目标市场', 'linked3')],
        'T2' => ['key' => 'T2', 'name' => __('结构', 'linked3'), 'fields' => __('叙事结构/章节划分/节奏', 'linked3')],
        'T3' => ['key' => 'T3', 'name' => __('角色', 'linked3'), 'fields' => __('主角/配角/用户画像', 'linked3')],
        'T4' => ['key' => 'T4', 'name' => __('语言', 'linked3'), 'fields' => __('语言风格/语调/用词', 'linked3')],
        'T5' => ['key' => 'T5', 'name' => __('节奏', 'linked3'), 'fields' => __('信息密度/场景切换/时长', 'linked3')],
        'T6' => ['key' => 'T6', 'name' => __('爽点', 'linked3'), 'fields' => __('痛点/利益点/情感共鸣', 'linked3')],
        'T7' => ['key' => 'T7', 'name' => __('伏笔', 'linked3'), 'fields' => __('暗示/铺垫/回收', 'linked3')],
        'T8' => ['key' => 'T8', 'name' => __('质量', 'linked3'), 'fields' => __('转化路径/可测试性/合规性', 'linked3')],
    ];

    /**
     * 类型-维度映射 (每类文本侧重的维度)
     */
    const TYPE_DIMENSION_MAP = [
        'novel' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8'],
        'poetry' => ['T1', 'T4', 'T5', 'T6', 'T7'],
        'ad' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T8'],
        'tech_doc' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T8'],
        'news' => ['T1', 'T2', 'T4', 'T5', 'T8'],
        'academic' => ['T1', 'T2', 'T4', 'T5', 'T8'],
        'copywriting' => ['T1', 'T4', 'T5', 'T6', 'T8'],
        'screenplay' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
        'comic_strip' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6'],
        'short_drama' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'],
    ];

    /**
     * 获取10类文本类型
     */
    public static function get_text_types(): array {
        return self::TEXT_TYPES;
    }

    /**
     * 获取类型对应的维度
     */
    public static function get_dimensions_for_type(string $type): array {
        $dim_keys = self::TYPE_DIMENSION_MAP[$type] ?? [];
        $result = [];
        foreach ($dim_keys as $key) {
            if (isset(self::TEXT_DIMENSIONS[$key])) {
                $result[$key] = self::TEXT_DIMENSIONS[$key];
            }
        }
        return $result;
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.6.0',
            'text_types_count' => count(self::TEXT_TYPES),
            'dimensions_count' => count(self::TEXT_DIMENSIONS),
            'source' => __('V18用篇28-30 逆向文本创作8维度', 'linked3'),
        ];
    }
}
