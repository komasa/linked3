<?php

declare(strict_types=1);
/**
 * Template Category Meta — feicai4.0 结构化字段组装
 *
 * v11.5.0 从 CloudTemplateFactory 拆出 (破局创新者方案)
 * 职责: 将分类 → feicai4.0 结构化字段 (profile/role/scene/.../output)
 *
 * @package Linked3\Classes\Content
 * @since 11.5.0
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

class TemplateCategoryMeta {

    /** @var CloudTemplateFactory */
    private $factory;

    public function __construct(CloudTemplateFactory $factory) {
        $this->factory = $factory;
    }

    /**
     * 获取模版 (含feicai4.0结构化字段)
     *
     * @param string $category 模版分类
     * @param string $topic    主题
     * @param array  $eco_context 生态上下文 (需要 tone 字段)
     */
    public function get_structured_template(string $category, string $topic, array $eco_context = []): array {
        $base = $this->factory->load_template_by_category($category);

        return [
            'profile' => ['author' => 'Linked3', 'version' => '10.5.3', 'desc' => $base['name'] ?? $category],
            'role' => $this->get_role($category),
            'scene' => $this->get_scene($category),
            'background' => sprintf('主题: %s', $topic),
            'goals' => $this->get_goals($category),
            'skills' => $this->get_skills($category),
            'style' => $eco_context['tone'] ?? 'professional',
            'limit' => $this->get_limits($category),
            'step' => $this->get_steps($category),
            'output' => $this->get_output_format($category),
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
