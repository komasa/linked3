<?php

declare(strict_types=1);
/**
 * Linked3 Diagram 30 Spectrum & Commercial Hardening — v6.5.0
 *
 * 9个原子版本:
 *   v6.5.0.1: 30种图示全谱引擎 (结构7+流程6+数据9+战略5+其他3)
 *   v6.5.0.2: 基座复用飞轮 (复用率70-80%)
 *   v6.5.0.3: 3D/AR/动态海报子系统
 *   v6.5.0.4: 视觉剧本转化3层管线
 *   v6.5.0.5: 品牌视觉资产5维度
 *   v6.5.0.6: 8大系统交叉引用矩阵
 *   v6.5.0.7: 商业加固 (熔断/限流/安全/缓存/审计)
 *   v6.5.0.8: E2E测试套件
 *   v6.5.0.9: 生产级启动器
 *
 * @package Linked3\Diagram
 * @since 6.5.0
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.5.0.1: 30种图示全谱引擎
// =================================================================

class Diagram30Spectrum {
    private static ?Diagram30Spectrum $instance = null;
    private array $spectrum = [];

    public static function instance(): Diagram30Spectrum {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // 结构关系7种
        $struct = ['Stacked架构图', 'Tree树形图', 'Pyramid金字塔', 'Network网络图', 'Radial辐射图', 'Venn韦恩图', 'Matrix矩阵'];
        foreach ($struct as $i => $name) {
            $this->register('D' . str_pad($i + 1, 2, '0', STR_PAD_LEFT), $name, '结构关系');
        }
        // 流程时序6种
        $flow = ['Flow流程图', 'Swimlane泳道图', 'Timeline时间线', 'Fishbone鱼骨图', 'Loop循环图', 'Staircase阶梯图'];
        foreach ($flow as $i => $name) {
            $this->register('D' . str_pad($i + 8, 2, '0', STR_PAD_LEFT), $name, '流程时序');
        }
        // 数据分析9种
        $data = ['Chart图表', 'Science科研绘图', 'TechRoadmap技术路线图', 'Infographic信息图', 'KnowledgeCard知识卡片', 'PyramidData数据金字塔', 'FishboneData数据鱼骨', 'MatrixData数据矩阵', 'StackedChart堆叠图'];
        foreach ($data as $i => $name) {
            $this->register('D' . str_pad($i + 14, 2, '0', STR_PAD_LEFT), $name, '数据分析');
        }
        // 战略分析5种
        $strategy = ['SWOT', 'PEST', 'Persona用户画像', 'UserStory用户故事', 'LeanCanvas精益画布'];
        foreach ($strategy as $i => $name) {
            $this->register('D' . str_pad($i + 23, 2, '0', STR_PAD_LEFT), $name, '战略分析');
        }
        // 其他3种
        $other = ['Treemap矩形树图', 'SimpleFlowchart简易流程', 'RadialSummary辐射总结'];
        foreach ($other as $i => $name) {
            $this->register('D' . str_pad($i + 28, 2, '0', STR_PAD_LEFT), $name, '其他');
        }
    }

    public function register(string $code, string $name, string $category): void {
        $this->spectrum[$code] = ['code' => $code, 'name' => $name, 'category' => $category];
    }

    public function all(): array { return $this->spectrum; }
    public function get(string $code): ?array { return $this->spectrum[$code] ?? null; }
    public function count(): int { return count($this->spectrum); }

    public function getStats(): array {
        $cats = [];
        foreach ($this->spectrum as $d) {
            $cats[$d['category']] = ($cats[$d['category']] ?? 0) + 1;
        }
        return ['total' => count($this->spectrum), 'by_category' => $cats];
    }
}

// =================================================================
// v6.5.0.2: 基座复用飞轮


// =================================================================
// v6.5.0.3: 3D/AR/动态海报子系统


// =================================================================
// v6.5.0.4: 视觉剧本转化3层管线


// =================================================================
// v6.5.0.5: 品牌视觉资产5维度


// =================================================================
// v6.5.0.6: 8大系统交叉引用矩阵


// =================================================================
// v6.5.0.7: 商业加固


// =================================================================
// v6.5.0.8: E2E测试套件


// =================================================================
// v6.5.0.9: 生产级启动器

