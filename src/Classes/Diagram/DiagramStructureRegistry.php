<?php

declare(strict_types=1);
/**
 * Diagram Structure Registry — v19.52 图示结构注册表（统一架构）.
 *
 * v19.52 重大优化（元杠杆 L06 抽象 + L05 批判 + L11 问题发现）:
 *   - 每个结构定义自己的 zones（区域）和 text_templates（文案模板）
 *   - 绞杀 charts-factory.php 中的 get_scene_visual_approach()（重复系统）
 *   - 绞杀 suggest_text_overlay() 的 4Band 硬编码（改为按结构 zones 生成）
 *   - 绞杀 quality_check() 的 4Band 硬编码（改为按结构 zones 检查）
 *
 * 核心抽象（L06）:
 *   每个结构 = {id, label, zones[], text_templates{}, prompt_template, layout_desc}
 *   zones 是结构的"骨架"，不同结构有不同骨架：
 *     4band:     [hook, body, proof, cta]
 *     timeline:  [era, milestone, event, outcome]
 *     flowchart: [start, step, decision, end]
 *     ...
 *
 * @package Linked3
 * @subpackage Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) {
    exit;
}

class DiagramStructureRegistry
{
    /** @var array<string, array> 注册的结构 */
    private static $structures = [];

    /** @var bool 是否已初始化 */
    private static $initialized = false;

    /**
     * 初始化：注册 8 种内置结构（每个含 zones + text_templates）.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // ═══════════════════════════════════════════════════════════════
        // 1. 4Band — 通用营销信息图（兜底）
        // ═══════════════════════════════════════════════════════════════
        self::register('4band', [
            'label'       => '4Band 信息图',
            'description' => 'Hook/Body/Proof/CTA 四层垂直布局，适合通用营销',
            'icon'        => '📊',
            'layout_desc' => '4Band vertical layout: top hook, middle body, lower proof, bottom CTA',
            'visual_keywords' => '4-band layout, hook zone, body zone, proof zone, CTA zone',
            'suitable_for' => ['marketing', 'general', 'cta-focused'],
            'prompt_template' => 'with 4Band vertical layout structure: [Top Hook zone] big title, strong contrast; [Middle Body zone] info points with icons; [Lower Proof zone] data charts; [Bottom CTA zone] action button.',
            'zones' => ['hook', 'body', 'proof', 'cta'],
            'text_templates' => [
                'hook'  => '{title}：关键要点速览',
                'body'  => '{summary}… 详细解读见下图',
                'proof' => '核心数据与政策依据：{title}',
                'cta'   => '收藏本页，随时查阅{title}',
            ],
        ]);

        // ═══════════════════════════════════════════════════════════════
        // 2. 时间轴 — 历程/节点/计划
        // ═══════════════════════════════════════════════════════════════
        self::register('timeline', [
            'label'       => '时间轴',
            'description' => '纵向里程碑布局，适合历程/节点/计划',
            'icon'        => '📅',
            'layout_desc' => 'vertical timeline with milestone markers and dates',
            'visual_keywords' => 'timeline, milestones, chronological, vertical axis, date markers',
            'suitable_for' => ['history', 'milestone', 'plan', 'schedule', '历程', '节点', '计划'],
            'prompt_template' => 'with vertical timeline layout: chronological milestones along a central vertical axis, each milestone has a date marker and event description card.',
            'zones' => ['era', 'milestone', 'event', 'outcome'],
            'text_templates' => [
                'era'       => '{title} · 时间阶段',
                'milestone' => '关键节点：{summary}',
                'event'     => '重要事件：{content_snippet}',
                'outcome'   => '阶段成果：{title}',
            ],
        ]);

        // ═══════════════════════════════════════════════════════════════
        // 3. 流程图 — 操作步骤/SOP
        // ═══════════════════════════════════════════════════════════════
        self::register('flowchart', [
            'label'       => '流程图',
            'description' => '横向箭头节点，适合操作步骤/SOP',
            'icon'        => '🔄',
            'layout_desc' => 'horizontal flowchart with numbered steps and arrows',
            'visual_keywords' => 'flowchart, step-by-step, arrows, process flow, numbered nodes',
            'suitable_for' => ['process', 'steps', 'sop', 'workflow', '流程', '步骤', '操作'],
            'prompt_template' => 'with horizontal flowchart layout: numbered step nodes connected by arrows, sequential process flow from left to right.',
            'zones' => ['start', 'step', 'decision', 'end'],
            'text_templates' => [
                'start'    => '开始：{title}',
                'step'     => '步骤：{summary}',
                'decision' => '判断：{content_snippet}',
                'end'      => '完成：{title}',
            ],
        ]);

        // ═══════════════════════════════════════════════════════════════
        // 4. 二栏对比 — A vs B
        // ═══════════════════════════════════════════════════════════════
        self::register('comparison', [
            'label'       => '二栏对比',
            'description' => '左右分栏 A vs B，适合对比分析',
            'icon'        => '⚖️',
            'layout_desc' => 'binary comparison layout with two columns A vs B',
            'visual_keywords' => 'comparison, VS layout, split screen, contrast columns, before after',
            'suitable_for' => ['compare', 'versus', 'before-after', 'pros-cons', '对比', '比较', '优劣'],
            'prompt_template' => 'with binary comparison layout: two columns side by side, left column A vs right column B, clear visual contrast between the two sides.',
            'zones' => ['left_title', 'left_items', 'right_title', 'right_items'],
            'text_templates' => [
                'left_title'   => '方案 A：{title}',
                'left_items'   => '特点：{summary}',
                'right_title'  => '方案 B：{title}',
                'right_items'  => '特点：{summary}',
            ],
        ]);

        // ═══════════════════════════════════════════════════════════════
        // 5. 数据图表 — 统计/比例/趋势
        // ═══════════════════════════════════════════════════════════════
        self::register('data_chart', [
            'label'       => '数据图表',
            'description' => '柱状/饼图/折线，适合统计/比例/趋势',
            'icon'        => '📈',
            'layout_desc' => 'data visualization with bar chart, pie chart, or line graph',
            'visual_keywords' => 'bar chart, pie chart, data visualization, statistics, percentage, trend line',
            'suitable_for' => ['data', 'statistics', 'percentage', 'trend', '数据', '统计', '比例', '百分比'],
            'prompt_template' => 'with data visualization layout: prominent bar chart or pie chart showing key statistics, percentage rings, trend indicators.',
            'zones' => ['chart_title', 'chart_data', 'chart_insight', 'data_source'],
            'text_templates' => [
                'chart_title'   => '{title} · 数据透视',
                'chart_data'    => '核心数据：{summary}',
                'chart_insight' => '数据洞察：{content_snippet}',
                'data_source'   => '数据来源：{title}',
            ],
        ]);

        // ═══════════════════════════════════════════════════════════════
        // 6. 编号清单 — 标准/规范/要点
        // ═══════════════════════════════════════════════════════════════
        self::register('checklist', [
            'label'       => '编号清单',
            'description' => '编号卡片列表，适合标准/规范/要点',
            'icon'        => '📋',
            'layout_desc' => 'checklist card layout with numbered badges',
            'visual_keywords' => 'checklist, numbered list, badge icons, card stack, organized items',
            'suitable_for' => ['standard', 'requirement', 'checklist', '规范', '标准', '要求', '清单'],
            'prompt_template' => 'with checklist card layout: numbered badges 01-08, organized list with icons, each item in a rounded card.',
            'zones' => ['list_title', 'item_1', 'item_2', 'item_3'],
            'text_templates' => [
                'list_title' => '{title} · 核心要点',
                'item_1'     => '要点一：{summary}',
                'item_2'     => '要点二：{content_snippet}',
                'item_3'     => '要点三：{title}',
            ],
        ]);

        // ═══════════════════════════════════════════════════════════════
        // 7. 思维导图 — 知识体系/概念
        // ═══════════════════════════════════════════════════════════════
        self::register('mindmap', [
            'label'       => '思维导图',
            'description' => '放射状节点，适合知识体系/概念',
            'icon'        => '🧠',
            'layout_desc' => 'radial mind map with central topic and branching sub-topics',
            'visual_keywords' => 'mind map, radial layout, branching nodes, knowledge graph, central hub',
            'suitable_for' => ['knowledge', 'concept', 'system', 'framework', '知识', '体系', '概念', '框架'],
            'prompt_template' => 'with radial mind map layout: central topic node with branching sub-topic nodes radiating outward, knowledge graph style.',
            'zones' => ['center', 'branch_1', 'branch_2', 'branch_3'],
            'text_templates' => [
                'center'    => '{title}',
                'branch_1'  => '分支一：{summary}',
                'branch_2'  => '分支二：{content_snippet}',
                'branch_3'  => '分支三：{title}',
            ],
        ]);

        // ═══════════════════════════════════════════════════════════════
        // 8. 引用卡片 — 金句/政策原文
        // ═══════════════════════════════════════════════════════════════
        self::register('quote_card', [
            'label'       => '引用卡片',
            'description' => '大字+小字，适合金句/政策原文',
            'icon'        => '💬',
            'layout_desc' => 'large quote card with prominent text overlay',
            'visual_keywords' => 'quote card, large text, bold typography, minimal design, text-focused',
            'suitable_for' => ['quote', 'policy', 'statement', '金句', '原文', '引用', '政策'],
            'prompt_template' => 'with quote card layout: large prominent quote text in center, smaller attribution text below, minimal visual elements.',
            'zones' => ['quote', 'attribution', 'context', 'source'],
            'text_templates' => [
                'quote'       => '"{content_snippet}"',
                'attribution' => '—— {title}',
                'context'     => '背景：{summary}',
                'source'      => '来源：{title}',
            ],
        ]);

        // 允许第三方扩展
        do_action('linked3_diagram_structures_registered', self::$structures);
    }

    /**
     * 注册一个结构.
     */
    public static function register(string $id, array $config): void
    {
        self::$structures[$id] = $config;
    }

    /**
     * 获取结构配置.
     */
    public static function get(string $id): ?array
    {
        return self::$structures[$id] ?? null;
    }

    /**
     * 获取所有结构.
     */
    public static function all(): array
    {
        return self::$structures;
    }

    /**
     * 智能匹配最适合的结构.
     *
     * v19.52: 增加位置感知（第1镜/最后镜/中间镜）
     */
    public static function match_best(string $content, int $scene_idx = 0, int $scene_total = 1): string
    {
        // v19.52: 位置感知
        $positionMatch = self::matchByPosition($content, $scene_idx, $scene_total);
        if ($positionMatch !== null) {
            return $positionMatch;
        }

        // 中间镜：按内容语义匹配
        $scores = self::scoreStructures($content);
        arsort($scores);
        $best = array_key_first($scores);

        if ($scores[$best] <= 0) {
            return '4band';
        }

        return $best;
    }

    /**
     * 位置感知匹配: 第1镜(封面) / 最后镜(总结) 特殊处理.
     *
     * @return string|null 结构ID, null 表示中间镜需继续语义匹配.
     */
    private static function matchByPosition(string $content, int $scene_idx, int $scene_total): ?string
    {
        if ($scene_total <= 1) {
            return null;
        }
        // 封面: 如果内容含金句/引用, 用 quote_card; 否则用 4band
        if ($scene_idx === 1) {
            if (preg_match('/"|"|「|『|指出|强调/', $content)) {
                return 'quote_card';
            }
            return '4band';
        }
        // 总结: 如果内容含要点/清单, 用 checklist; 否则用 4band
        if ($scene_idx === $scene_total) {
            if (preg_match('/\d+\.\s|第[一二三四五六七八九十]+|条|项|要求/', $content)) {
                return 'checklist';
            }
            return '4band';
        }
        return null;
    }

    /**
     * 按内容语义为所有结构评分.
     *
     * @return array<string,int>
     */
    private static function scoreStructures(string $content): array
    {
        $scores = [];
        foreach (self::$structures as $id => $structure) {
            $scores[$id] = 0;
            $suitable = $structure['suitable_for'] ?? [];
            foreach ($suitable as $keyword) {
                if (mb_strpos($content, $keyword) !== false) {
                    $scores[$id] += 10;
                }
            }
            // 特殊模式检测
            $scores[$id] += self::scoreStructurePattern($id, $content);
        }
        return $scores;
    }

    /**
     * 为单个结构类型计算特殊模式加分.
     */
    private static function scoreStructurePattern(string $id, string $content): int
    {
        switch ($id) {
            case 'timeline':
                return preg_match('/\d{4}年|\d+月|阶段|步骤/', $content) ? 5 : 0;
            case 'data_chart':
                return preg_match('/\d+%|\d+万|\d+亿|占比|比例/', $content) ? 5 : 0;
            case 'comparison':
                return preg_match('/对比|vs|VS|相比|区别|差异/', $content) ? 5 : 0;
            case 'checklist':
                return preg_match('/\d+\.\s|第[一二三四五六七八九十]+|条|项|要求/', $content) ? 5 : 0;
            case 'flowchart':
                return preg_match('/流程|步骤|先.*再|首先.*然后/', $content) ? 5 : 0;
            case 'quote_card':
                return preg_match('/"|"|「|『|指出|强调|表示/', $content) ? 5 : 0;
            case 'mindmap':
                return preg_match('/包括|包含|分为|类别|方面/', $content) ? 5 : 0;
            default:
                return 0;
        }
    }

    /**
     * v19.52: 为指定结构的指定 zone 生成文案.
     *
     * 使用结构的 text_templates 模板，用 segment 数据填充占位符。
     *
     * @param string $structure_id 结构 ID
     * @param string $zone_key     zone 键名
     * @param array  $segment      {title, summary, content}
     * @return string
     */
    public static function suggest_text(string $structure_id, string $zone_key, array $segment): string
    {
        $structure = self::get($structure_id);
        if (!$structure) {
            return $segment['title'] ?? '';
        }

        $templates = $structure['text_templates'] ?? [];
        $template = $templates[$zone_key] ?? '{title}';

        // 准备替换变量
        $title = $segment['title'] ?? '';
        $summary = $segment['summary'] ?? '';
        $content = $segment['content'] ?? '';
        $content_snippet = mb_substr($content, 0, 30);
        $keyword = mb_substr($title, 0, 15);

        // 执行替换
        $text = strtr($template, [
            '{title}'            => $title,
            '{summary}'          => $summary,
            '{content_snippet}'  => $content_snippet,
            '{keyword}'          => $keyword,
        ]);

        return $text;
    }
}
