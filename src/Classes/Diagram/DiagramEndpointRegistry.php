<?php

declare(strict_types=1);
/**
 * Linked3 Diagram Endpoint & Followup System — v6.3.0
 *
 * 9个原子版本:
 *   v6.3.0.1: 6种Endpoint注册表 (Mountain/Flywheel/Spiral/Compound/Ecosystem/Transformation)
 *   v6.3.0.2: Endpoint选择决策树
 *   v6.3.0.3: 6种追问类型 (实战/决策/诊断/预测/追问/觉察)
 *   v6.3.0.4: 4种Footer类型 (价值观/方法论/原则/公式)
 *   v6.3.0.5: Footer×追问兼容性矩阵
 *   v6.3.0.6: 6种关系编码 (→/~>/<->/━/┄)
 *   v6.3.0.7: 6级认知标注 ([R][U][A][An][E][C])
 *   v6.3.0.8: 4档信息密度 (极简/标准/深度/极致)
 *   v6.3.0.9: 第9维度视觉频率 ([HF][MF][LF])
 *
 * @package Linked3\Diagram
 * @since 6.3.0
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.3.0.1: 6种Endpoint注册表
// =================================================================

class DiagramEndpointRegistry {
    private static ?DiagramEndpointRegistry $instance = null;
    private array $endpoints = [];

    public static function instance(): DiagramEndpointRegistry {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register('Mountain path', [
            'name_zh' => '山峰路径', 'name_en' => 'Mountain path',
            'meaning' => '修行成长 (挑战→攀登→登顶)',
            'visual' => 'Mountain path with 4 milestones',
            'milestones' => 4,
            'emotion' => '成就感',
            'suitable_for' => '个人成长/技能进阶/团队发展',
        ]);
        $this->register('Flywheel', [
            'name_zh' => '飞轮', 'name_en' => 'Flywheel',
            'meaning' => '商业飞轮 (4要素互相加速)',
            'visual' => '4 gears circular acceleration',
            'milestones' => 4,
            'emotion' => '闭环感',
            'suitable_for' => '商业模式/增长引擎/正循环',
        ]);
        $this->register('Growth spiral', [
            'name_zh' => '增长螺旋', 'name_en' => 'Growth spiral',
            'meaning' => '迭代进化 (螺旋上升)',
            'visual' => 'Spiral with 4 milestones rising',
            'milestones' => 4,
            'emotion' => '进化感',
            'suitable_for' => '产品迭代/技术演进/认知升级',
        ]);
        $this->register('Compound curve', [
            'name_zh' => '复利曲线', 'name_en' => 'Compound curve',
            'meaning' => '复利积累 (S曲线拐点)',
            'visual' => 'S-curve with inflection point and 4 milestones',
            'milestones' => 4,
            'emotion' => '积累感',
            'suitable_for' => '投资/知识积累/技能复利',
        ]);
        $this->register('Ecosystem loop', [
            'name_zh' => '生态循环', 'name_en' => 'Ecosystem loop',
            'meaning' => '生态共生 (多节点闭环)',
            'visual' => 'Multi-node ecosystem with 4 milestones',
            'milestones' => 4,
            'emotion' => '共生感',
            'suitable_for' => '生态体系/产业链/平台经济',
        ]);
        $this->register('Transformation path', [
            'name_zh' => '转型路径', 'name_en' => 'Transformation path',
            'meaning' => '转型蜕变 (茧→蝶)',
            'visual' => 'Cocoon->butterfly with 3 stage markers',
            'milestones' => 3,
            'emotion' => '蜕变感',
            'suitable_for' => '企业转型/个人蜕变/品牌升级',
        ]);
    }

    public function register(string $id, array $config): void {
        $this->endpoints[$id] = array_merge(['id' => $id], $config);
    }

    public function get(string $id): ?array { return $this->endpoints[$id] ?? null; }
    public function all(): array { return $this->endpoints; }

    public function getVisual(string $id): string {
        return $this->endpoints[$id]['visual'] ?? '4 gears circular acceleration';
    }
}

// =================================================================
// v6.3.0.2: Endpoint选择决策树


// =================================================================
// v6.3.0.3: 6种追问类型


// =================================================================
// v6.3.0.4: 4种Footer类型


// =================================================================
// v6.3.0.5: Footer×追问兼容性矩阵


// =================================================================
// v6.3.0.6: 6种关系编码


// =================================================================
// v6.3.0.7: 6级认知标注


// =================================================================
// v6.3.0.8: 4档信息密度


// =================================================================
// v6.3.0.9: 第9维度视觉频率

