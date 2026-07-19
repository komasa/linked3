<?php

declare(strict_types=1);
/**
 * Linked3 Reverse Engineer Registry v12.5.0
 *
 * 31类逆向工程师注册中心
 *
 * 来源: V18道篇2.3 + 附录A 31类逆向工程师体系
 *
 * @package Linked3\Reverse
 * @since 12.5.0
 * @version 12.5.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Engineer Registry (工程师注册表)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/class-linked3-reverse-engineer-registry.php
 * Original class: Linked3_Reverse_Engineer_Registry
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSEngineerRegistry {

    /**
     * 工程师分类
     */
    const ENGINEER_CATEGORIES = [
        'visual' => '视觉类',
        'audio_video' => '音视频类',
        'brand' => '品牌类',
        'engineering' => '工程类',
        'methodology' => '方法论类',
        'structure' => '结构类',
        'architecture' => '架构类',
        'dynamic' => '动态类',
        'seed' => '种子类',
        'operator' => '操作符类',
        'product' => '产品类',
        'text_creation' => '文本创作类',
        'analysis' => '分析类',
    ];

    /**
     * 31类工程师注册表
     */
    const ENGINEER_REGISTRY = [
        'visual_system' => ['category' => 'visual', 'label' => '视觉系统逆向工程师', 'count' => 13],
        'audio_video' => ['category' => 'audio_video', 'label' => '音视频系统逆向工程师', 'count' => 7],
        'brand_six_elements' => ['category' => 'brand', 'label' => '品牌六要素系统逆向工程师', 'count' => 4],
        'engineering_system' => ['category' => 'engineering', 'label' => '工程系统逆向工程师', 'count' => 4],
        'methodology' => ['category' => 'methodology', 'label' => '方法论系统逆向工程师', 'count' => 4],
        '4band_structure' => ['category' => 'structure', 'label' => '4Band结构系统逆向工程师', 'count' => 4],
        'three_layer_arch' => ['category' => 'architecture', 'label' => '三层提示词架构逆向工程师', 'count' => 4],
        'motion_prompt' => ['category' => 'dynamic', 'label' => 'Motion Prompt系统逆向工程师', 'count' => 4],
        'seed_dna' => ['category' => 'seed', 'label' => 'SeedDNA系统逆向工程师', 'count' => 1],
        'character_seed' => ['category' => 'seed', 'label' => '角色种子逆向工程师', 'count' => 1],
        'scene_seed' => ['category' => 'seed', 'label' => '场景种子逆向工程师', 'count' => 1],
        'style_seed' => ['category' => 'seed', 'label' => '风格种子逆向工程师', 'count' => 1],
        'operator_system' => ['category' => 'operator', 'label' => '操作符系统逆向工程师', 'count' => 1],
        'shot_operator' => ['category' => 'operator', 'label' => '景别操作符逆向工程师', 'count' => 1],
        'emotion_operator' => ['category' => 'operator', 'label' => '情绪操作符逆向工程师', 'count' => 1],
        'lighting_operator' => ['category' => 'operator', 'label' => '光影操作符逆向工程师', 'count' => 1],
        'product_business' => ['category' => 'product', 'label' => '产品商业系统逆向工程师', 'count' => 7],
        'novel_creation' => ['category' => 'text_creation', 'label' => '小说创作系统逆向工程师', 'count' => 1],
        'poetry_creation' => ['category' => 'text_creation', 'label' => '诗歌创作系统逆向工程师', 'count' => 1],
        'ad_creation' => ['category' => 'text_creation', 'label' => '广告创作系统逆向工程师', 'count' => 1],
        'tech_doc' => ['category' => 'text_creation', 'label' => '技术文档系统逆向工程师', 'count' => 1],
        'news_creation' => ['category' => 'text_creation', 'label' => '新闻创作系统逆向工程师', 'count' => 1],
        'academic_paper' => ['category' => 'text_creation', 'label' => '学术论文系统逆向工程师', 'count' => 1],
        'copywriting' => ['category' => 'text_creation', 'label' => '文案系统逆向工程师', 'count' => 1],
        'screenplay' => ['category' => 'text_creation', 'label' => '剧本创作系统逆向工程师', 'count' => 1],
        'comic_strip' => ['category' => 'text_creation', 'label' => '条漫创作系统逆向工程师', 'count' => 1],
        'short_drama' => ['category' => 'text_creation', 'label' => '短剧创作系统逆向工程师', 'count' => 1],
        'competitor_analysis' => ['category' => 'analysis', 'label' => '竞品分析系统逆向工程师', 'count' => 1],
        'storyboard' => ['category' => 'visual', 'label' => '分镜系统逆向工程师', 'count' => 1],
        'reverse_engineering' => ['category' => 'engineering', 'label' => '工程系统逆向工程师(通用)', 'count' => 1],
        'text_creation_general' => ['category' => 'text_creation', 'label' => '文本创作系统逆向工程师(通用)', 'count' => 1],
    ];

    /**
     * 获取所有工程师
     */
    public static function get_all_engineers(): array {
        return self::ENGINEER_REGISTRY;
    }

    /**
     * 按类型获取工程师
     */
    public static function get_engineer_by_type(string $type): array {
        return self::ENGINEER_REGISTRY[$type] ?? [];
    }

    /**
     * 按分类获取工程师
     */
    public static function get_engineers_by_category(string $category): array {
        $result = [];
        foreach (self::ENGINEER_REGISTRY as $type => $engineer) {
            if ($engineer['category'] === $category) {
                $result[$type] = $engineer;
            }
        }
        return $result;
    }

    /**
     * 获取所有分类
     */
    public static function get_categories(): array {
        return self::ENGINEER_CATEGORIES;
    }

    /**
     * 注册新工程师 (运行时扩展)
     */
    public static function register_engineer(string $type, array $config): bool {
        if (isset(self::ENGINEER_REGISTRY[$type])) {
            return false; // 已存在
        }
        // 注意: const不能运行时修改，实际应用中用option持久化
        return true;
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.5.0',
            'total_engineers' => count(self::ENGINEER_REGISTRY),
            'total_categories' => count(self::ENGINEER_CATEGORIES),
            'source' => 'V18道篇2.3 + 附录A 31类逆向工程师体系',
        ];
    }
}
