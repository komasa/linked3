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
 * Original class: Linked3_Reverse_Text_Creation
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSTextCreation {

    /**
     * 10类文本类型
     */
    const TEXT_TYPES = [
        'novel' => '小说创作',
        'poetry' => '诗歌创作',
        'ad' => '广告创作',
        'tech_doc' => '技术文档',
        'news' => '新闻创作',
        'academic' => '学术论文',
        'copywriting' => '文案',
        'screenplay' => '剧本',
        'comic_strip' => '条漫',
        'short_drama' => '短剧',
    ];

    /**
     * T1-T8文本专属8维度
     */
    const TEXT_DIMENSIONS = [
        'T1' => ['key' => 'T1', 'name' => '题材', 'fields' => '类型/领域/目标市场'],
        'T2' => ['key' => 'T2', 'name' => '结构', 'fields' => '叙事结构/章节划分/节奏'],
        'T3' => ['key' => 'T3', 'name' => '角色', 'fields' => '主角/配角/用户画像'],
        'T4' => ['key' => 'T4', 'name' => '语言', 'fields' => '语言风格/语调/用词'],
        'T5' => ['key' => 'T5', 'name' => '节奏', 'fields' => '信息密度/场景切换/时长'],
        'T6' => ['key' => 'T6', 'name' => '爽点', 'fields' => '痛点/利益点/情感共鸣'],
        'T7' => ['key' => 'T7', 'name' => '伏笔', 'fields' => '暗示/铺垫/回收'],
        'T8' => ['key' => 'T8', 'name' => '质量', 'fields' => '转化路径/可测试性/合规性'],
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
     * 获取T1-T8维度
     */
    public static function get_text_dimensions(): array {
        return self::TEXT_DIMENSIONS;
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
     * 构建文本逆向Prompt
     */
    public static function build_text_reverse_prompt(string $type, string $target): string {
        $type_label = self::TEXT_TYPES[$type] ?? '文本';
        $dims = self::get_dimensions_for_type($type);

        $prompt = "你是专业" . $type_label . "系统逆向工程师。请对以下文本进行深度拆解。\n\n";
        $prompt .= "【目标文本】\n" . $target . "\n\n";
        $prompt .= "【文本专属8维度】\n\n";

        foreach ($dims as $dim) {
            $prompt .= $dim['key'] . "_" . $dim['name'] . "：" . $dim['fields'] . "\n\n";
        }

        $prompt .= "输出纯JSON，所有字段必填。";
        return $prompt;
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.6.0',
            'text_types_count' => count(self::TEXT_TYPES),
            'dimensions_count' => count(self::TEXT_DIMENSIONS),
            'source' => 'V18用篇28-30 逆向文本创作8维度',
        ];
    }
}
