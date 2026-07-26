<?php

declare(strict_types=1);
/**
 * Linked3 Diagram Type Registry — v6.1.0.2
 *
 * 16种图示类型注册表:
 *   结构类6种: Stacked/Funnel/Flow/Network/Pyramid/Loop
 *   扩展类4种: Staircase/Mountain/Tree/Matrix
 *   高阶类6种: Timeline/Swimlane/Radial/Fishbone/Venn/GrowthCurve
 *
 * 每种图示包含:
 *   - 图示名称+英文标识
 *   - 适合的信息结构
 *   - 3层嵌入映射
 *   - 选择决策树节点
 *
 * @package Linked3\Diagram
 * @since 6.1.0.2
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramTypeRegistry {
    private static ?DiagramTypeRegistry $instance = null;
    private array $types = [];
    private array $decisionTree = [];

    public static function instance(): DiagramTypeRegistry {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->registerDefaults();
    }

    private function registerDefaults(): void {
        // ===== 结构类6种 =====
        $this->register('Stacked', [
            'name_zh' => __('堆叠块', 'linked3'), 'name_en' => 'Stacked blocks',
            'category' => __('结构类', 'linked3'),
            'info_structure' => __('层次递进关系 (上层依赖下层)', 'linked3'),
            'embed_3layer' => ['layer1' => __('模块标题', 'linked3'), 'layer2' => __('每层子主题', 'linked3'), 'layer3' => __('细节项嵌入块内', 'linked3')],
            'decision_node' => __('信息有明确层级递进?', 'linked3'),
            'badge_suggest' => '01',
            'prompt_fragment' => 'Stacked rectangular blocks, 3-4 layers, bottom-up or top-down hierarchy, text EMBEDDED inside each block.',
        ]);

        $this->register('Funnel', [
            'name_zh' => __('三层漏斗', 'linked3'), 'name_en' => '3-layer funnel',
            'category' => __('结构类', 'linked3'),
            'info_structure' => __('逐步筛选/转化关系 (宽→窄)', 'linked3'),
            'embed_3layer' => ['layer1' => __('漏斗标题', 'linked3'), 'layer2' => __('每层筛选标准', 'linked3'), 'layer3' => __('转化率嵌入层间', 'linked3')],
            'decision_node' => __('信息有筛选/转化流失?', 'linked3'),
            'badge_suggest' => '02',
            'prompt_fragment' => 'Funnel shape with 3 layers, wide top narrowing to bottom, conversion rates between layers, text EMBEDDED.',
        ]);

        $this->register('Flow', [
            'name_zh' => __('线性流程', 'linked3'), 'name_en' => 'Linear flow',
            'category' => __('结构类', 'linked3'),
            'info_structure' => __('顺序步骤 (A→B→C→D)', 'linked3'),
            'embed_3layer' => ['layer1' => __('流程标题', 'linked3'), 'layer2' => __('每步骤名称', 'linked3'), 'layer3' => __('操作细节嵌入箭头旁', 'linked3')],
            'decision_node' => __('信息是线性步骤?', 'linked3'),
            'badge_suggest' => '03',
            'prompt_fragment' => 'Linear flow with 4-5 connected boxes, left-to-right arrows, step numbers, text EMBEDDED in boxes.',
        ]);

        $this->register('Network', [
            'name_zh' => __('网络节点', 'linked3'), 'name_en' => 'Network node',
            'category' => __('结构类', 'linked3'),
            'info_structure' => __('多对多关联 (网状)', 'linked3'),
            'embed_3layer' => ['layer1' => __('网络标题', 'linked3'), 'layer2' => __('中心节点+卫星节点', 'linked3'), 'layer3' => __('连接关系嵌入线上', 'linked3')],
            'decision_node' => __('信息是多对多关联?', 'linked3'),
            'badge_suggest' => '04',
            'prompt_fragment' => 'Network graph with central node and 4-6 satellite nodes, connecting lines with labels, text EMBEDDED.',
        ]);

        $this->register('Pyramid', [
            'name_zh' => __('三层金字塔', 'linked3'), 'name_en' => '3-layer pyramid',
            'category' => __('结构类', 'linked3'),
            'info_structure' => __('优先级/重要性递减 (顶→底)', 'linked3'),
            'embed_3layer' => ['layer1' => __('金字塔标题', 'linked3'), 'layer2' => __('每层主题', 'linked3'), 'layer3' => __('占比/权重嵌入层内', 'linked3')],
            'decision_node' => __('信息有优先级层次?', 'linked3'),
            'badge_suggest' => '05',
            'prompt_fragment' => '3-layer pyramid, top narrow bottom wide, hierarchy levels, text EMBEDDED inside each layer.',
        ]);

        $this->register('Loop', [
            'name_zh' => __('循环闭环', 'linked3'), 'name_en' => 'Circular loop',
            'category' => __('结构类', 'linked3'),
            'info_structure' => __('循环反馈 (A→B→C→A)', 'linked3'),
            'embed_3layer' => ['layer1' => __('循环标题', 'linked3'), 'layer2' => __('每环节名称', 'linked3'), 'layer3' => __('反馈机制嵌入弧线', 'linked3')],
            'decision_node' => __('信息是循环反馈?', 'linked3'),
            'badge_suggest' => '06',
            'prompt_fragment' => 'Circular loop with 4-5 nodes, curved arrows forming closed cycle, text EMBEDDED.',
        ]);

        // ===== 扩展类4种 =====
        $this->register('Staircase', [
            'name_zh' => __('上升阶梯', 'linked3'), 'name_en' => 'Ascending staircase',
            'category' => __('扩展类', 'linked3'),
            'info_structure' => __('逐步提升 (低→高)', 'linked3'),
            'embed_3layer' => ['layer1' => __('阶梯标题', 'linked3'), 'layer2' => __('每级台阶名称', 'linked3'), 'layer3' => __('提升量嵌入台阶上', 'linked3')],
            'decision_node' => __('信息有逐步提升?', 'linked3'),
            'badge_suggest' => '07',
            'prompt_fragment' => 'Ascending staircase with 4-5 steps rising left to right, step labels, text EMBEDDED.',
        ]);

        $this->register('Mountain', [
            'name_zh' => __('山峰路径', 'linked3'), 'name_en' => 'Mountain path',
            'category' => __('扩展类', 'linked3'),
            'info_structure' => __('挑战→攀登→登顶 (弧线上升)', 'linked3'),
            'embed_3layer' => ['layer1' => __('山峰标题', 'linked3'), 'layer2' => __('每阶段里程碑', 'linked3'), 'layer3' => __('挑战/成就嵌入路径', 'linked3')],
            'decision_node' => __('信息有挑战→成就弧线?', 'linked3'),
            'badge_suggest' => '08',
            'prompt_fragment' => 'Mountain path with 4 milestones, ascending trail, peak at top, text EMBEDDED.',
        ]);

        $this->register('Tree', [
            'name_zh' => __('树形图', 'linked3'), 'name_en' => 'Tree diagram',
            'category' => __('扩展类', 'linked3'),
            'info_structure' => __('分类展开 (根→枝→叶)', 'linked3'),
            'embed_3layer' => ['layer1' => __('树标题', 'linked3'), 'layer2' => __('分支主题', 'linked3'), 'layer3' => __('叶节点嵌入', 'linked3')],
            'decision_node' => __('信息有分类展开?', 'linked3'),
            'badge_suggest' => '09',
            'prompt_fragment' => 'Tree diagram with root node, 3-4 branches, leaf nodes, text EMBEDDED.',
        ]);

        $this->register('Matrix', [
            'name_zh' => __('矩阵2×2', 'linked3'), 'name_en' => 'Matrix 2x2',
            'category' => __('扩展类', 'linked3'),
            'info_structure' => __('双维度交叉 (4象限)', 'linked3'),
            'embed_3layer' => ['layer1' => __('矩阵标题', 'linked3'), 'layer2' => __('象限名称', 'linked3'), 'layer3' => __('特征嵌入象限内', 'linked3')],
            'decision_node' => __('信息可双维度分类?', 'linked3'),
            'badge_suggest' => '01',
            'prompt_fragment' => '2x2 matrix with axis labels, 4 quadrants, text EMBEDDED in each quadrant.',
        ]);

        // ===== 高阶类6种 =====
        $this->register('Timeline', [
            'name_zh' => __('时间线', 'linked3'), 'name_en' => 'Timeline',
            'category' => __('高阶类', 'linked3'),
            'info_structure' => __('时间序列 (过去→现在→未来)', 'linked3'),
            'embed_3layer' => ['layer1' => __('时间线标题', 'linked3'), 'layer2' => __('时间节点', 'linked3'), 'layer3' => __('事件嵌入节点旁', 'linked3')],
            'decision_node' => __('信息有时间序列?', 'linked3'),
            'badge_suggest' => '02',
            'prompt_fragment' => 'Horizontal timeline with 5-6 milestones, dates, events, text EMBEDDED.',
        ]);

        $this->register('Swimlane', [
            'name_zh' => __('泳道图', 'linked3'), 'name_en' => 'Swimlane',
            'category' => __('高阶类', 'linked3'),
            'info_structure' => __('多角色并行流程', 'linked3'),
            'embed_3layer' => ['layer1' => __('泳道标题', 'linked3'), 'layer2' => __('角色泳道', 'linked3'), 'layer3' => __('操作嵌入泳道内', 'linked3')],
            'decision_node' => __('信息涉及多角色?', 'linked3'),
            'badge_suggest' => '03',
            'prompt_fragment' => 'Swimlane diagram with 3-4 lanes (roles), flow steps across lanes, text EMBEDDED.',
        ]);

        $this->register('Radial', [
            'name_zh' => __('辐射思维导图', 'linked3'), 'name_en' => 'Radial mindmap',
            'category' => __('高阶类', 'linked3'),
            'info_structure' => __('中心发散 (中心→四周)', 'linked3'),
            'embed_3layer' => ['layer1' => __('导图标题', 'linked3'), 'layer2' => __('主分支', 'linked3'), 'layer3' => __('子节点嵌入', 'linked3')],
            'decision_node' => __('信息有中心发散?', 'linked3'),
            'badge_suggest' => '04',
            'prompt_fragment' => 'Radial mindmap with central topic, 5-6 main branches, sub-nodes, text EMBEDDED.',
        ]);

        $this->register('Fishbone', [
            'name_zh' => __('鱼骨图', 'linked3'), 'name_en' => 'Fishbone',
            'category' => __('高阶类', 'linked3'),
            'info_structure' => __('因果分析 (结果←原因)', 'linked3'),
            'embed_3layer' => ['layer1' => __('鱼骨标题', 'linked3'), 'layer2' => __('主骨分类', 'linked3'), 'layer3' => __('原因嵌入支骨', 'linked3')],
            'decision_node' => __('信息需要因果分析?', 'linked3'),
            'badge_suggest' => '05',
            'prompt_fragment' => 'Fishbone diagram with main spine, 4-6 branch bones, cause labels, text EMBEDDED.',
        ]);

        $this->register('Venn', [
            'name_zh' => __('韦恩图', 'linked3'), 'name_en' => 'Venn diagram',
            'category' => __('高阶类', 'linked3'),
            'info_structure' => __('集合交集/差异', 'linked3'),
            'embed_3layer' => ['layer1' => __('韦恩标题', 'linked3'), 'layer2' => __('集合名称', 'linked3'), 'layer3' => __('特征嵌入交集区', 'linked3')],
            'decision_node' => __('信息有交集关系?', 'linked3'),
            'badge_suggest' => '06',
            'prompt_fragment' => 'Venn diagram with 3 overlapping circles, set labels, intersection text EMBEDDED.',
        ]);

        $this->register('GrowthCurve', [
            'name_zh' => __('增长曲线', 'linked3'), 'name_en' => 'Growth curve',
            'category' => __('高阶类', 'linked3'),
            'info_structure' => __('趋势变化 (时间→数值)', 'linked3'),
            'embed_3layer' => ['layer1' => __('曲线标题', 'linked3'), 'layer2' => __('阶段名称', 'linked3'), 'layer3' => __('数值嵌入曲线点', 'linked3')],
            'decision_node' => __('信息有趋势变化?', 'linked3'),
            'badge_suggest' => '07',
            'prompt_fragment' => 'Growth curve with S-shape, 4 phase labels, data points, text EMBEDDED.',
        ]);

        // 构建决策树
        $this->buildDecisionTree();
    }

    /**
     * 注册图示类型。
     */
    public function register(string $id, array $config): void {
        $this->types[$id] = array_merge(['id' => $id], $config);
    }

    /**
     * 获取图示类型。
     */
    public function get(string $id): ?array {
        return $this->types[$id] ?? null;
    }

    /**
     * 获取所有图示类型。
     */
    public function all(): array {
        return $this->types;
    }

    /**
     * 构建决策树。
     */
    private function buildDecisionTree(): void {
        $this->decisionTree = [
            '层级递进' => 'Stacked',
            '筛选转化' => 'Funnel',
            '线性步骤' => 'Flow',
            '多对多' => 'Network',
            '优先级' => 'Pyramid',
            '循环反馈' => 'Loop',
            '逐步提升' => 'Staircase',
            '挑战成就' => 'Mountain',
            '分类展开' => 'Tree',
            '双维度' => 'Matrix',
            '时间序列' => 'Timeline',
            '多角色' => 'Swimlane',
            '中心发散' => 'Radial',
            '因果分析' => 'Fishbone',
            '交集关系' => 'Venn',
            '趋势变化' => 'GrowthCurve',
        ];
    }

    /**
     * 按信息结构匹配图示类型 (决策树)。
     */
    public function selectByInfoStructure(string $infoStructure): ?string {
        foreach ($this->decisionTree as $keyword => $typeId) {
            if (strpos($infoStructure, $keyword) !== false) {
                return $typeId;
            }
        }
        return 'Stacked'; // 默认
    }

    /**
     * 获取统计。
     */
    public function getStats(): array {
        $cats = [];
        foreach ($this->types as $t) {
            $cat = $t['category'];
            $cats[$cat] = ($cats[$cat] ?? 0) + 1;
        }
        return ['total' => count($this->types), 'by_category' => $cats];
    }
}
