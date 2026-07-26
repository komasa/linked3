<?php

declare(strict_types=1);
/**
 * Template Variant Manager — 行业变体管理 + 跨生态模版桥接
 *
 * v11.5.0 从 CloudTemplateFactory 拆出 (破局创新者方案)
 * 职责:
 *   - 行业变体母版 (load_template_by_category_and_industry)
 *   - 行业元数据 (get_industries)
 *   - 分类×行业完整矩阵 (get_all_variants_for_category)
 *   - 跨生态桥接 (get_shared_template_for_script)
 *
 * @package Linked3\Classes\Content
 * @since 11.5.0
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

class TemplateVariantManager {

    /** @var CloudTemplateFactory */
    private $factory;

    /** @var TemplateCategoryMeta */
    private $meta;

    public function __construct(CloudTemplateFactory $factory, TemplateCategoryMeta $meta) {
        $this->factory = $factory;
        $this->meta = $meta;
    }

    /**
     * v11.4.1 行业多元化母版库 (G3方案②)
     *
     * 公理ζ: 每类母版扩充行业变体, 多场景化储备
     * 10类 × 5行业 = 50个场景化母版, 零破坏原有 load_template_by_category
     *
     * @param string $category 模版分类
     * @param string $industry 行业变体 (general/ecommerce/education/tech/medical)
     * @return array 行业化母版
     */
    public function load_template_by_category_and_industry(string $category, string $industry = 'general'): array {
        // general 或空 → 回退原有逻辑 (零破坏)
        if (empty($industry) || $industry === 'general') {
            return $this->factory->load_template_by_category($category);
        }

        $base = $this->factory->load_template_by_category($category);
        $industry_overrides = $this->get_industry_overrides($category, $industry);

        if (empty($industry_overrides)) {
            return $base;
        }

        // 深度合并: 行业变体覆盖基础母版的config
        $merged = $base;
        $merged['name'] = ($base['name'] ?? $category) . ' · ' . $industry_overrides['_industry_label'];
        $merged['industry'] = $industry;
        if (isset($base['config']) && isset($industry_overrides['config'])) {
            $merged['config'] = array_merge($base['config'], $industry_overrides['config']);
        }
        return $merged;
    }

    /**
     * v11.4.1 获取行业变体配置
     */
    private function get_industry_overrides(string $category, string $industry): array {
        $industry_labels = [
            'ecommerce' => __('电商', 'linked3'), 'education' => __('教育', 'linked3'),
            'tech' => __('科技', 'linked3'), 'medical' => __('医疗', 'linked3'),
        ];

        // 行业 × 角色调性矩阵
        $industry_profiles = [
            'ecommerce' => [
                'role_suffix' => __('（电商转化导向）', 'linked3'),
                'goals_extra' => ['转化引导', '信任建立', '购买决策'],
                'style' => __('热情专业', 'linked3'),
                'limit_extra' => ['含CTA', '突出卖点', '价格敏感度标注'],
            ],
            'education' => [
                'role_suffix' => __('（知识教学导向）', 'linked3'),
                'goals_extra' => ['知识传递', '循序渐进', '练习巩固'],
                'style' => __('清晰耐心', 'linked3'),
                'limit_extra' => ['含示例', '难度递进', '知识点标注'],
            ],
            'tech' => [
                'role_suffix' => __('（技术深度导向）', 'linked3'),
                'goals_extra' => ['原理剖析', '最佳实践', '避坑指南'],
                'style' => __('严谨极客', 'linked3'),
                'limit_extra' => ['含代码', '版本标注', '性能考量'],
            ],
            'medical' => [
                'role_suffix' => __('（循证合规导向）', 'linked3'),
                'goals_extra' => ['循证依据', '风险提示', '合规免责'],
                'style' => __('严谨客观', 'linked3'),
                'limit_extra' => ['含文献', '免责声明', '非诊断提示'],
            ],
        ];

        $profile = $industry_profiles[$industry] ?? null;
        if (!$profile) {
            return [];
        }

        $base_config = $this->factory->load_template_by_category($category);
        $base_config = $base_config['config'] ?? [];

        return [
            '_industry_label' => $industry_labels[$industry] ?? $industry,
            'config' => [
                'role' => ($base_config['role'] ?? '内容创作者') . $profile['role_suffix'],
                'goals' => array_merge($base_config['goals'] ?? [], $profile['goals_extra']),
                'style' => $profile['style'],
                'limit' => array_merge($base_config['limit'] ?? [], $profile['limit_extra']),
            ],
        ];
    }

    /**
     * v11.4.1 获取所有行业变体列表 (供UI展示)
     */
    public function get_industries(): array {
        return [
            'general'   => ['label' => __('通用', 'linked3'), 'icon' => '📋'],
            'ecommerce' => ['label' => __('电商', 'linked3'), 'icon' => '🛒'],
            'education' => ['label' => __('教育', 'linked3'), 'icon' => '📚'],
            'tech'      => ['label' => __('科技', 'linked3'), 'icon' => '⚙️'],
            'medical'   => ['label' => __('医疗', 'linked3'), 'icon' => '⚕️'],
        ];
    }

    /**
     * v11.4.1 获取某分类下的所有母版(含行业变体) — 供云模版页展示完整矩阵
     */
    public function get_all_variants_for_category(string $category): array {
        $variants = [];
        foreach ($this->get_industries() as $ind_slug => $ind_meta) {
            $tpl = $this->load_template_by_category_and_industry($category, $ind_slug);
            $variants[] = [
                'category' => $category,
                'industry' => $ind_slug,
                'industry_label' => $ind_meta['label'],
                'industry_icon' => $ind_meta['icon'],
                'name' => $tpl['name'] ?? ($category . '_' . $ind_slug),
                'template' => $tpl,
            ];
        }
        return $variants;
    }

    /**
     * v10.7.0 跨生态共享桥接 (公理R)
     *
     * 将云模版转换为脚本生态(charts/genesis/video)可消费的统一格式:
     *   - style:    风格描述 (对应 Genesis_StyleEngine 的 style 字段)
     *   - structure: 结构骨架 (对应 script_factory 的 structure 字段)
     *   - palette:  色彩/情绪调色板 (对应 charts 的 palette 字段)
     *   - tone:     语气基调 (跨生态一致)
     *   - source:   来源标记, 便于溯源
     *
     * @param string $category 模版分类 (content/seo/social/video/comic)
     * @param string $script_type 脚本类型 (charts/genesis/video)
     * @param array  $eco_context 生态上下文 (需要 topic 字段)
     * @return array 脚本生态可消费的模版配置
     */
    public function get_shared_template_for_script(string $category, string $script_type = 'charts', array $eco_context = []): array {
        $base = $this->factory->load_template_by_category($category);
        $structured = $this->meta->get_structured_template($category, $eco_context['topic'] ?? '', $eco_context);

        // 脚本类型 → 调色板映射
        $palette_map = [
            'charts'  => ['primary' => '#3b82f6', 'secondary' => '#22c55e', 'accent' => '#f59e0b', 'bg' => '#ffffff'],
            'genesis' => ['primary' => '#1e40af', 'secondary' => '#7c3aed', 'accent' => '#ec4899', 'bg' => '#fef3c7'],
            'video'   => ['primary' => '#dc2626', 'secondary' => '#f59e0b', 'accent' => '#06b6d4', 'bg' => '#0f172a'],
        ];

        // 脚本类型 → 结构骨架映射
        $structure_map = [
            'charts'  => ['title', 'subtitle', 'legend', 'axis', 'series', 'annotation'],
            'genesis' => ['cover', 'panels', 'characters', 'dialogues', 'actions', 'ending'],
            'video'   => ['hook', 'intro', 'body', 'climax', 'cta', 'outro'],
        ];

        return [
            'style'     => $structured['style'] ?? 'professional',
            'structure' => $structure_map[$script_type] ?? $structure_map['charts'],
            'palette'   => $palette_map[$script_type] ?? $palette_map['charts'],
            'tone'      => $structured['style'] ?? 'professional',
            'role'      => $structured['role'] ?? '',
            'goals'     => $structured['goals'] ?? [],
            'limits'    => $structured['limit'] ?? [],
            'source'    => [
                'factory'   => 'CloudTemplateFactory',
                'category'  => $category,
                'script'    => $script_type,
                'template'  => $base['name'] ?? $category . '_default',
                'version'   => '10.7.0',
                'shared_at' => current_time('mysql'),
            ],
        ];
    }
}
