<?php

declare(strict_types=1);
/**
 * Linked3 Cloud Template Factory — 云模版工厂 (Trait版)
 *
 * v10.5.3 (G5) 新增: 云模版工厂, 使用Content_Ecosystem_Trait
 * v10.7.0  重铸: 新增跨生态共享桥接 (公理R), 写作生态与脚本生态共享同一云模版池
 *
 * 移植 feicai4.0 结构化提示词模板:
 *   • Profile/Role/Scene/Background/Goals/Skills/Style/Limit/Step/Output
 *   • 模版分类: content/seo/social/video/comic
 *   • 模版继承: fork_starter机制
 *
 * 跨生态共享 (v10.7.0):
 *   • get_shared_template_for_script() — 输出脚本生态可消费的 style/structure/palette
 *   • 脚本工厂通过 ScriptFactoryBase::consume_cloud_template() 拉取
 *
 * @package Linked3\Content
 * @since 10.5.3
 * @version 10.7.0
 */

namespace Linked3\Classes\Content;
    use ContentEcosystemTrait;



if (!defined('ABSPATH')) exit;

if (!trait_exists('ContentEcosystemTrait')) {
    require_once __DIR__ . '/ContentEcosystem.php';
}

class CloudTemplateFactory {
    /** @var array feicai4.0 结构化模版字段 */
    private $template_fields = [
        'profile', 'role', 'scene', 'background',
        'goals', 'skills', 'style', 'limit', 'step', 'output',
    ];

    /** @var array 模版分类 (v11.1.0: 补齐genesis/tutorial/review/news, 共10类) */
    private $categories = ['content', 'seo', 'social', 'video', 'comic', 'charts', 'genesis', 'tutorial', 'review', 'news'];

    public function __construct() {
        $this->module_type = 'cloud_template';
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

    /**
     * 获取模版 (含feicai4.0结构化字段)
     */
    public function get_structured_template(string $category, string $topic): array {
        $base = $this->load_template_by_category($category);

        // 填充feicai4.0结构化字段
        return [
            'profile' => ['author' => 'Linked3', 'version' => '10.5.3', 'desc' => $base['name'] ?? $category],
            'role' => $this->get_role($category),
            'scene' => $this->get_scene($category),
            'background' => sprintf('主题: %s', $topic),
            'goals' => $this->get_goals($category),
            'skills' => $this->get_skills($category),
            'style' => $this->eco_context['tone'] ?? 'professional',
            'limit' => $this->get_limits($category),
            'step' => $this->get_steps($category),
            'output' => $this->get_output_format($category),
        ];
    }

    /**
     * 获取所有模版分类
     */
    public function get_categories(): array {
        return $this->categories;
    }

    /**
     * fork模版 (基于现有模版创建变体)
     */
    public function fork_template(string $category, array $overrides): array {
        $base = $this->load_template_by_category($category);
        return array_merge($base, $overrides);
    }

    /**
     * v11.4.1 行业多元化母版库 (G3方案②)
     *
     * 公理ζ: 每类母版扩充行业变体, 多场景化储备
     * 10类 × 5行业 = 50个场景化母版, 零破坏原有 load_template_by_category
     *
     * 行业维度:
     *   - general  通用 (回退到原有母版)
     *   - ecommerce 电商 (商品描述/转化导向)
     *   - education 教育 (知识体系/教学导向)
     *   - tech     科技 (技术深度/极客导向)
     *   - medical  医疗 (严谨合规/循证导向)
     *
     * @param string $category 模版分类
     * @param string $industry 行业变体 (general/ecommerce/education/tech/medical)
     * @return array 行业化母版
     */
    public function load_template_by_category_and_industry(string $category, string $industry = 'general'): array
    {
        // general 或空 → 回退原有逻辑 (零破坏)
        if (empty($industry) || $industry === 'general') {
            return $this->load_template_by_category($category);
        }

        $base = $this->load_template_by_category($category);
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
    private function get_industry_overrides(string $category, string $industry): array
    {
        $industry_labels = [
            'ecommerce' => '电商', 'education' => '教育',
            'tech' => '科技', 'medical' => '医疗',
        ];

        // 行业 × 角色调性矩阵
        $industry_profiles = [
            'ecommerce' => [
                'role_suffix' => '（电商转化导向）',
                'goals_extra' => ['转化引导', '信任建立', '购买决策'],
                'style' => '热情专业',
                'limit_extra' => ['含CTA', '突出卖点', '价格敏感度标注'],
            ],
            'education' => [
                'role_suffix' => '（知识教学导向）',
                'goals_extra' => ['知识传递', '循序渐进', '练习巩固'],
                'style' => '清晰耐心',
                'limit_extra' => ['含示例', '难度递进', '知识点标注'],
            ],
            'tech' => [
                'role_suffix' => '（技术深度导向）',
                'goals_extra' => ['原理剖析', '最佳实践', '避坑指南'],
                'style' => '严谨极客',
                'limit_extra' => ['含代码', '版本标注', '性能考量'],
            ],
            'medical' => [
                'role_suffix' => '（循证合规导向）',
                'goals_extra' => ['循证依据', '风险提示', '合规免责'],
                'style' => '严谨客观',
                'limit_extra' => ['含文献', '免责声明', '非诊断提示'],
            ],
        ];

        $profile = $industry_profiles[$industry] ?? null;
        if (!$profile) {
            return [];
        }

        $base_config = $this->load_template_by_category($category);
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
    public function get_industries(): array
    {
        return [
            'general'   => ['label' => '通用', 'icon' => '📋'],
            'ecommerce' => ['label' => '电商', 'icon' => '🛒'],
            'education' => ['label' => '教育', 'icon' => '📚'],
            'tech'      => ['label' => '科技', 'icon' => '⚙️'],
            'medical'   => ['label' => '医疗', 'icon' => '⚕️'],
        ];
    }

    /**
     * v11.4.1 获取某分类下的所有母版(含行业变体) — 供云模版页展示完整矩阵
     */
    public function get_all_variants_for_category(string $category): array
    {
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
     * @return array 脚本生态可消费的模版配置
     */
    public function get_shared_template_for_script(string $category, string $script_type = 'charts'): array {
        $base = $this->load_template_by_category($category);
        $structured = $this->get_structured_template($category, $this->eco_context['topic'] ?? '');

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

    /**
     * v10.7.0 批量获取脚本生态可用模版列表
     *
     * @param string $script_type 脚本类型 (charts/genesis/video)
     * @return array 模版列表 [{category, name, style, palette_preview}]
     */
    public function list_shared_templates_for_script(string $script_type = 'charts'): array {
        $list = [];
        foreach ($this->categories as $cat) {
            $shared = $this->get_shared_template_for_script($cat, $script_type);
            $list[] = [
                'category'        => $cat,
                'name'            => $shared['source']['template'],
                'style'           => $shared['style'],
                'palette_preview' => $shared['palette']['primary'] ?? '#3b82f6',
            ];
        }
        return $list;
    }

    public function load_template_by_category(string $category): array {
        // v10.7.3: 内置母版库 (6类完整模版)
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
            // v11.1.0 #2: 补充genesis漫画脚本母版 (用户反馈显示不全)
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
            // v11.1.0 #2: 补充更多实用母版
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

    private function get_role(string $category): string {
        $roles = [
            'content' => '专业内容创作者',
            'seo' => 'SEO优化专家',
            'social' => '社交媒体运营',
            'video' => '视频脚本编剧',
            'comic' => '漫画脚本作者',
            'charts' => '图示内容设计师',
            'genesis' => '漫画分镜作者',
            'tutorial' => '技术教程作者',
            'review' => '产品评测师',
            'news' => '科技资讯编辑',
        ];
        return $roles[$category] ?? '内容创作者';
    }

    private function get_scene(string $category): string {
        $scenes = [
            'content' => '博客文章/公众号/知识库',
            'seo' => '搜索引擎优化场景',
            'social' => '小红书/微博/抖音',
            'video' => '短视频/中视频',
            'comic' => '漫画分镜',
            'charts' => '信息图/图文卡片',
            'genesis' => '漫画分镜/连环画',
            'tutorial' => '教程/攻略/How-to',
            'review' => '产品评测/对比测评',
            'news' => '新闻资讯/快讯',
        ];
        return $scenes[$category] ?? '通用内容';
    }

    private function get_goals(string $category): array {
        $goals = [
            'content' => ['信息传递', '价值输出', 'SEO友好'],
            'seo' => ['关键词覆盖', '排名提升', '点击率优化'],
            'social' => ['互动引导', '种草转化', '传播扩散'],
            'video' => ['3秒钩子', '完播率', '互动引导'],
            'comic' => ['分镜清晰', '角色一致', '剧情推进'],
            'charts' => ['吸引注意', '信息传递', '行动号召'],
            'genesis' => ['分镜清晰', '角色一致', '剧情推进', '画面表现力'],
            'tutorial' => ['教会读者', '步骤清晰', '可操作', 'FAQ解答'],
            'review' => ['客观评测', '数据对比', '优缺点分析', '购买建议'],
            'news' => ['信息传递', '时效性', '要点突出', '引发关注'],
        ];
        return $goals[$category] ?? ['内容产出'];
    }

    private function get_skills(string $category): array {
        $skills = [
            'content' => ['结构化写作', '关键词布局', '用户洞察', '数据驱动'],
            'seo' => ['关键词研究', '竞品分析', '内容优化', '数据驱动'],
            'social' => ['情绪共鸣', '场景化描述', '互动设计', '标签策略'],
            'video' => ['分镜设计', '旁白撰写', '字幕精炼', '节奏控制'],
            'comic' => ['分镜设计', '角色塑造', '对话撰写', '画面描述'],
            'charts' => ['4Band结构', '视觉设计', '文案精炼', '品牌一致'],
            'genesis' => ['分镜设计', '角色塑造', '对话撰写', '画面描述', '节奏控制'],
            'tutorial' => ['步骤拆解', '截图标注', '代码示例', '错误提示'],
            'review' => ['产品分析', '数据对比', '优缺点提炼', '结论给出'],
            'news' => ['信息提炼', '标题制作', '要点总结', '背景补充'],
        ];
        return $skills[$category] ?? ['结构化写作'];
    }

    private function get_limits(string $category): array {
        $limits = [
            'content' => ['字数800-2000', '段落≤5行', 'H2≥3个'],
            'seo' => ['关键词密度2-3%', '标题≤60字', '描述≤160字'],
            'social' => ['标题≤22字', '正文≤1000字', '标签5-8个'],
            'video' => ['时长60-180秒', '每15秒转折', '3秒钩子'],
            'comic' => ['分镜5-10个', '每镜1图', '角色DNA一致'],
            'charts' => ['模块4-8个', '每模块1图', '文字精炼'],
            'genesis' => ['分镜5-15个', '每镜1图', '角色DNA一致', '对话简洁'],
            'tutorial' => ['步骤≥5步', '每步配截图', '代码可复制'],
            'review' => ['对比维度≥5个', '有数据支撑', '结论明确'],
            'news' => ['字数300-800', '导语≤50字', '要点3-5个'],
        ];
        return $limits[$category] ?? ['字数适中'];
    }

    private function get_steps(string $category): array {
        $steps = [
            'content' => ['选题定位', '关键词收集', '大纲构建', '正文撰写', '质检优化'],
            'seo' => ['关键词分析', '竞品研究', '标题优化', '内容撰写', 'Meta优化'],
            'social' => ['选题定位', '标题钩子', '正文撰写', '标签优化', '发布建议'],
            'video' => ['选题定位', '分镜设计', '旁白撰写', '字幕精炼', '品牌闭环'],
            'comic' => ['角色设计', '分镜规划', '对话撰写', '画面描述', '质检优化'],
            'charts' => ['内容拆分', '4Band分配', '视觉Prompt', '文字叠加', '布局优化'],
            'genesis' => ['角色设计', '分镜规划', '对话撰写', '画面描述', '质检优化'],
            'tutorial' => ['需求分析', '环境准备', '步骤拆解', '截图标注', 'FAQ补充'],
            'review' => ['产品概述', '维度选择', '对比分析', '优缺点总结', '购买建议'],
            'news' => ['信息收集', '要点提炼', '导语撰写', '正文展开', '背景补充'],
        ];
        return $steps[$category] ?? ['选题', '撰写', '质检'];
    }

    private function get_output_format(string $category): string {
        $formats = [
            'content' => 'Markdown格式, 含H1/H2/H3',
            'seo' => '标题+描述+正文+关键词列表',
            'social' => '标题+正文+标签+发布建议',
            'video' => '分镜脚本+台词+动作指导',
            'comic' => '分镜列表+Prompt+角色DNA',
            'charts' => '模块列表+视觉Prompt+文字叠加',
            'genesis' => '分镜列表+Prompt+角色DNA+对话',
            'tutorial' => '步骤列表+截图+代码+FAQ',
            'review' => '对比表格+优缺点+购买建议',
            'news' => '标题+导语+要点+正文',
        ];
        return $formats[$category] ?? 'Markdown';
    }
}
