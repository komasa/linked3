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
 * Original file: src/Classes/V18/Core/ReverseEngineerRegistry.php
 * Original class: OSEngineerRegistry
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSEngineerRegistry {

    /**
     * 工程师分类
     */
    const ENGINEER_CATEGORIES = [
        'visual' => __('视觉类', 'linked3'),
        'audio_video' => __('音视频类', 'linked3'),
        'brand' => __('品牌类', 'linked3'),
        'engineering' => __('工程类', 'linked3'),
        'methodology' => __('方法论类', 'linked3'),
        'structure' => __('结构类', 'linked3'),
        'architecture' => __('架构类', 'linked3'),
        'dynamic' => __('动态类', 'linked3'),
        'seed' => __('种子类', 'linked3'),
        'operator' => __('操作符类', 'linked3'),
        'product' => __('产品类', 'linked3'),
        'text_creation' => __('文本创作类', 'linked3'),
        'analysis' => __('分析类', 'linked3'),
    ];

    /**
     * 31类工程师注册表
     */
    const ENGINEER_REGISTRY = [
        'visual_system' => ['category' => 'visual', 'label' => __('视觉系统逆向工程师', 'linked3'), 'count' => 13],
        'audio_video' => ['category' => 'audio_video', 'label' => __('音视频系统逆向工程师', 'linked3'), 'count' => 7],
        'brand_six_elements' => ['category' => 'brand', 'label' => __('品牌六要素系统逆向工程师', 'linked3'), 'count' => 4],
        'engineering_system' => ['category' => 'engineering', 'label' => __('工程系统逆向工程师', 'linked3'), 'count' => 4],
        'methodology' => ['category' => 'methodology', 'label' => __('方法论系统逆向工程师', 'linked3'), 'count' => 4],
        '4band_structure' => ['category' => 'structure', 'label' => __('4Band结构系统逆向工程师', 'linked3'), 'count' => 4],
        'three_layer_arch' => ['category' => 'architecture', 'label' => __('三层提示词架构逆向工程师', 'linked3'), 'count' => 4],
        'motion_prompt' => ['category' => 'dynamic', 'label' => __('Motion Prompt系统逆向工程师', 'linked3'), 'count' => 4],
        'seed_dna' => ['category' => 'seed', 'label' => __('SeedDNA系统逆向工程师', 'linked3'), 'count' => 1],
        'character_seed' => ['category' => 'seed', 'label' => __('角色种子逆向工程师', 'linked3'), 'count' => 1],
        'scene_seed' => ['category' => 'seed', 'label' => __('场景种子逆向工程师', 'linked3'), 'count' => 1],
        'style_seed' => ['category' => 'seed', 'label' => __('风格种子逆向工程师', 'linked3'), 'count' => 1],
        'operator_system' => ['category' => 'operator', 'label' => __('操作符系统逆向工程师', 'linked3'), 'count' => 1],
        'shot_operator' => ['category' => 'operator', 'label' => __('景别操作符逆向工程师', 'linked3'), 'count' => 1],
        'emotion_operator' => ['category' => 'operator', 'label' => __('情绪操作符逆向工程师', 'linked3'), 'count' => 1],
        'lighting_operator' => ['category' => 'operator', 'label' => __('光影操作符逆向工程师', 'linked3'), 'count' => 1],
        'product_business' => ['category' => 'product', 'label' => __('产品商业系统逆向工程师', 'linked3'), 'count' => 7],
        'novel_creation' => ['category' => 'text_creation', 'label' => __('小说创作系统逆向工程师', 'linked3'), 'count' => 1],
        'poetry_creation' => ['category' => 'text_creation', 'label' => __('诗歌创作系统逆向工程师', 'linked3'), 'count' => 1],
        'ad_creation' => ['category' => 'text_creation', 'label' => __('广告创作系统逆向工程师', 'linked3'), 'count' => 1],
        'tech_doc' => ['category' => 'text_creation', 'label' => __('技术文档系统逆向工程师', 'linked3'), 'count' => 1],
        'news_creation' => ['category' => 'text_creation', 'label' => __('新闻创作系统逆向工程师', 'linked3'), 'count' => 1],
        'academic_paper' => ['category' => 'text_creation', 'label' => __('学术论文系统逆向工程师', 'linked3'), 'count' => 1],
        'copywriting' => ['category' => 'text_creation', 'label' => __('文案系统逆向工程师', 'linked3'), 'count' => 1],
        'screenplay' => ['category' => 'text_creation', 'label' => __('剧本创作系统逆向工程师', 'linked3'), 'count' => 1],
        'comic_strip' => ['category' => 'text_creation', 'label' => __('条漫创作系统逆向工程师', 'linked3'), 'count' => 1],
        'short_drama' => ['category' => 'text_creation', 'label' => __('短剧创作系统逆向工程师', 'linked3'), 'count' => 1],
        'competitor_analysis' => ['category' => 'analysis', 'label' => __('竞品分析系统逆向工程师', 'linked3'), 'count' => 1],
        'storyboard' => ['category' => 'visual', 'label' => __('分镜系统逆向工程师', 'linked3'), 'count' => 1],
        'reverse_engineering' => ['category' => 'engineering', 'label' => __('工程系统逆向工程师(通用)', 'linked3'), 'count' => 1],
        'text_creation_general' => ['category' => 'text_creation', 'label' => __('文本创作系统逆向工程师(通用)', 'linked3'), 'count' => 1],
    ];

    /**
     * 获取所有工程师
     */
    public static function get_all_engineers(): array {
        return self::ENGINEER_REGISTRY;
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
            'source' => __('V18道篇2.3 + 附录A 31类逆向工程师体系', 'linked3'),
        ];
    }
}
