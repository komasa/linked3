<?php

declare(strict_types=1);
/**
 * Linked3 Cloud Template Factory — 云模版工厂 (门面)
 *
 * v10.5.3 (G5) 新增: 云模版工厂, 使用Content_Ecosystem_Trait
 * v10.7.0  重铸: 新增跨生态共享桥接 (公理R)
 * v11.5.0  拆分: 提取 TemplateCategoryMeta + TemplateVariantManager (破局创新者方案)
 *
 * 职责 (拆分后):
 *   • 门面: 保持所有 public API 不变, 透明委托给协作类
 *   • Trait实现: generate_content / generate_image
 *   • 数据: load_template_by_category (内置母版库)
 *
 * 协作类:
 *   • TemplateCategoryMeta     — feicai4.0 结构化字段组装
 *   • TemplateVariantManager   — 行业变体 + 跨生态桥接
 *
 * @package Linked3\Classes\Content
 * @since 10.5.3
 * @version 11.5.0
 */

namespace Linked3\Classes\Content;
    use ContentEcosystemTrait;


if (!defined('ABSPATH')) exit;

if (!trait_exists('ContentEcosystemTrait')) {
    require_once __DIR__ . '/ContentEcosystemTrait.php';
}

class CloudTemplateFactory {
    /** @var array 模版分类 (v11.1.0: 共10类) */
    private $categories = ['content', 'seo', 'social', 'video', 'comic', 'charts', 'genesis', 'tutorial', 'review', 'news'];

    /** @var TemplateCategoryMeta */
    private $meta;

    /** @var TemplateVariantManager */
    private $variants;

    public function __construct() {
        $this->module_type = 'cloud_template';
        $this->meta = new TemplateCategoryMeta($this);
        $this->variants = new TemplateVariantManager($this, $this->meta);
    }

    /**
     * 生成正文 — 模版模块产出模版配置 (作为content的形态指导)
     */
    protected function generate_content(array $ir): string {
        $topic = $this->eco_context['topic'] ?? '';
        $category = $this->eco_context['category'] ?? 'content';

        $template = $this->load_template_by_category($category);
        $this->content_ir['template'] = $template;

        return $template['content'] ?? '';
    }

    /**
     * 生成配图 — 模版模块不直接生成图片
     */
    protected function generate_image(array $ir): array {
        return [];
    }

    // ─── 委托方法 (保持 public 签名不变, 外部调用方零修改) ───

    public function get_structured_template(string $category, string $topic): array {
        return $this->meta->get_structured_template($category, $topic, $this->eco_context);
    }

    public function get_categories(): array {
        return $this->categories;
    }

    public function load_template_by_category_and_industry(string $category, string $industry = 'general'): array {
        return $this->variants->load_template_by_category_and_industry($category, $industry);
    }

    public function get_industries(): array {
        return $this->variants->get_industries();
    }

    public function get_all_variants_for_category(string $category): array {
        return $this->variants->get_all_variants_for_category($category);
    }

    public function get_shared_template_for_script(string $category, string $script_type = 'charts'): array {
        return $this->variants->get_shared_template_for_script($category, $script_type, $this->eco_context);
    }

    // ─── 内置母版库 ───

    public function load_template_by_category(string $category): array {
        // v10.7.3: 内置母版库 (10类完整模版)
        $builtin_masters = [
            'content' => [
                'name' => '通用文章模版',
                'type' => 'content',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v10.7 | 通用文章',
                    'role' => '专业内容创作者',
                    'scene' => '博客文章/公众号/知识库',
                    'background' => '面向搜索引擎和读者, 兼顾SEO与可读性',
                    'goals' => ['信息传递', '价值输出', 'SEO友好'],
                    'skills' => ['结构化写作', '关键词布局', '用户洞察', '数据驱动'],
                    'style' => '专业',
                    'limit' => ['字数800-2000', '段落≤5行', 'H2≥3个'],
                    'step' => ['选题定位', '关键词收集', '大纲构建', '正文撰写', '质检优化'],
                    'output' => 'Markdown格式, 含H1/H2/H3',
                ],
            ],
            'seo' => [
                'name' => 'SEO优化模版',
                'type' => 'seo',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v10.7 | SEO优化',
                    'role' => 'SEO优化专家',
                    'scene' => '搜索引擎优化场景',
                    'background' => '以关键词为核心, 提升搜索排名和点击率',
                    'goals' => ['关键词覆盖', '排名提升', '点击率优化'],
                    'skills' => ['关键词研究', '竞品分析', '内容优化', '数据驱动'],
                    'style' => '专业',
                    'limit' => ['关键词密度2-3%', '标题≤60字', '描述≤160字'],
                    'step' => ['关键词分析', '竞品研究', '标题优化', '内容撰写', 'Meta优化'],
                    'output' => '标题+描述+正文+关键词列表',
                ],
            ],
            'social' => [
                'name' => '社媒种草模版',
                'type' => 'social',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v10.7 | 社媒种草',
                    'role' => '社交媒体运营',
                    'scene' => '小红书/微博/抖音',
                    'background' => '以用户互动为核心, 引导种草和转化',
                    'goals' => ['互动引导', '种草转化', '传播扩散'],
                    'skills' => ['情绪共鸣', '场景化描述', '互动设计', '标签策略'],
                    'style' => '轻松',
                    'limit' => ['标题≤22字', '正文≤1000字', '标签5-8个'],
                    'step' => ['选题定位', '标题钩子', '正文撰写', '标签优化', '发布建议'],
                    'output' => '标题+正文+标签+发布建议',
                ],
            ],
            'video' => [
                'name' => '视频脚本模版',
                'type' => 'video',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v10.7 | 视频脚本',
                    'role' => '视频脚本编剧',
                    'scene' => '短视频/中视频',
                    'background' => '9页SOP分镜结构, 3秒钩子+完播率优化',
                    'goals' => ['3秒钩子', '完播率', '互动引导'],
                    'skills' => ['分镜设计', '旁白撰写', '字幕精炼', '节奏控制'],
                    'style' => '口语化',
                    'limit' => ['时长60-180秒', '每15秒转折', '3秒钩子'],
                    'step' => ['选题定位', '分镜设计', '旁白撰写', '字幕精炼', '品牌闭环'],
                    'output' => '分镜脚本+台词+动作指导',
                ],
            ],
            'comic' => [
                'name' => '漫画分镜模版',
                'type' => 'comic',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v10.7 | 漫画分镜',
                    'role' => '漫画脚本作者',
                    'scene' => '漫画分镜',
                    'background' => '分镜清晰, 角色DNA一致, 剧情推进',
                    'goals' => ['分镜清晰', '角色一致', '剧情推进'],
                    'skills' => ['分镜设计', '角色塑造', '对话撰写', '画面描述'],
                    'style' => '叙事',
                    'limit' => ['分镜5-10个', '每镜1图', '角色DNA一致'],
                    'step' => ['角色设计', '分镜规划', '对话撰写', '画面描述', '质检优化'],
                    'output' => '分镜列表+Prompt+角色DNA',
                ],
            ],
            'charts' => [
                'name' => '图示脚本模版',
                'type' => 'charts',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v10.7 | 图示脚本',
                    'role' => '图示内容设计师',
                    'scene' => '信息图/图文卡片',
                    'background' => '4Band结构(Hook/Body/Proof/CTA), 信息密度高',
                    'goals' => ['吸引注意', '信息传递', '行动号召'],
                    'skills' => ['4Band结构', '视觉设计', '文案精炼', '品牌一致'],
                    'style' => '信息图',
                    'limit' => ['模块4-8个', '每模块1图', '文字精炼'],
                    'step' => ['内容拆分', '4Band分配', '视觉Prompt', '文字叠加', '布局优化'],
                    'output' => '模块列表+视觉Prompt+文字叠加',
                ],
            ],
            // v11.1.0 #2: 补充genesis漫画脚本母版
            'genesis' => [
                'name' => '漫画分镜母版',
                'type' => 'genesis',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v11.1 | 漫画分镜',
                    'role' => '漫画脚本作者',
                    'scene' => '漫画分镜/连环画',
                    'background' => '分镜清晰, 角色DNA一致, 剧情推进, 适合多格漫画',
                    'goals' => ['分镜清晰', '角色一致', '剧情推进', '画面表现力'],
                    'skills' => ['分镜设计', '角色塑造', '对话撰写', '画面描述', '节奏控制'],
                    'style' => '叙事',
                    'limit' => ['分镜5-15个', '每镜1图', '角色DNA一致', '对话简洁'],
                    'step' => ['角色设计', '分镜规划', '对话撰写', '画面描述', '质检优化'],
                    'output' => '分镜列表+Prompt+角色DNA+对话',
                ],
            ],
            'tutorial' => [
                'name' => '教程攻略母版',
                'type' => 'tutorial',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v11.1 | 教程攻略',
                    'role' => '技术教程作者',
                    'scene' => '教程/攻略/How-to',
                    'background' => '面向学习者, 步骤清晰, 可操作性强',
                    'goals' => ['教会读者', '步骤清晰', '可操作', '常见问题解答'],
                    'skills' => ['步骤拆解', '截图标注', '代码示例', '常见错误提示'],
                    'style' => '教程',
                    'limit' => ['步骤≥5步', '每步配截图', '代码可复制'],
                    'step' => ['需求分析', '环境准备', '步骤拆解', '截图标注', 'FAQ补充'],
                    'output' => '步骤列表+截图+代码+FAQ',
                ],
            ],
            'review' => [
                'name' => '评测对比母版',
                'type' => 'review',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v11.1 | 评测对比',
                    'role' => '产品评测师',
                    'scene' => '产品评测/对比测评',
                    'background' => '客观对比, 数据支撑, 帮助用户决策',
                    'goals' => ['客观评测', '数据对比', '优缺点分析', '购买建议'],
                    'skills' => ['产品分析', '数据对比', '优缺点提炼', '结论给出'],
                    'style' => '客观',
                    'limit' => ['对比维度≥5个', '有数据支撑', '结论明确'],
                    'step' => ['产品概述', '维度选择', '对比分析', '优缺点总结', '购买建议'],
                    'output' => '对比表格+优缺点+购买建议',
                ],
            ],
            'news' => [
                'name' => '资讯快讯母版',
                'type' => 'news',
                'is_builtin' => true,
                'config' => [
                    'profile' => 'Linked3 v11.1 | 资讯快讯',
                    'role' => '科技资讯编辑',
                    'scene' => '新闻资讯/快讯',
                    'background' => '时效性强, 要点突出, 适合快速阅读',
                    'goals' => ['信息传递', '时效性', '要点突出', '引发关注'],
                    'skills' => ['信息提炼', '标题制作', '要点总结', '背景补充'],
                    'style' => '新闻',
                    'limit' => ['字数300-800', '导语≤50字', '要点3-5个'],
                    'step' => ['信息收集', '要点提炼', '导语撰写', '正文展开', '背景补充'],
                    'output' => '标题+导语+要点+正文',
                ],
            ],
        ];

        // 返回内置母版
        if (isset($builtin_masters[$category])) {
            return $builtin_masters[$category];
        }

        // 委托 Template_Manager (若存在)
        if (class_exists('\Linked3\Classes\Content\TemplateManager')) {
            try {
                $mgr = new \TemplateManager();
                if (method_exists($mgr, 'get_by_category')) {
                    $templates = $mgr->get_by_category($category);
                    if (!empty($templates)) return $templates[0];
                }
            } catch (\Throwable $e) {}
        }

        // 降级: 默认模版
        return [
            'name' => $category . '_default',
            'type' => $category,
            'is_builtin' => true,
            'config' => [
                'profile' => 'Linked3 v10.7 | ' . $category,
                'role' => '内容创作者',
                'scene' => '通用场景',
                'background' => '',
                'goals' => ['内容产出'],
                'skills' => ['结构化写作'],
                'style' => 'professional',
                'limit' => ['字数适中'],
                'step' => ['选题', '撰写', '质检'],
                'output' => 'Markdown',
            ],
        ];
    }
}
