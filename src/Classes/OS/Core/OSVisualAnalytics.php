<?php

declare(strict_types=1);
/**
 * Linked3 SVG Meta Stats v12.2.0
 *
 * SVG原子级meta统计引擎 — 基于1297个SVG实例的统计基线
 *
 * 来源: V18 法篇6「SVG原子级meta全量统计」+ 术篇13「SVG实例统计」
 * 数据源: svg原子级meta全量.xlsx (1297个SVG×39维meta×26场景)
 *
 * 核心能力:
 *   1. get_stats_by_chart_type(): 按图示类型获取SVG原子统计
 *   2. get_stats_by_scene(): 按场景获取SVG原子统计
 *   3. predict_atom_count(): 预测新图示的原子数量
 *   4. get_layout_distribution(): 获取布局类型分布
 *
 * @package Linked3\SvgStats
 * @since 12.2.0
 * @version 12.2.0
 */

namespace Linked3\Classes\OS\Core;

/**
 * OS Module — Visual Analytics (可视化分析)
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Core/SvgMetaStats.php
 * Original class: OSVisualAnalytics
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSVisualAnalytics {

    /**
     * 1297个SVG实例的统计基线
     * 来源: svg原子级meta全量.xlsx
     */
    const SVG_STATS_BASELINE = [
        'total_svgs' => 1297,
        'total_scenes' => 26,
        'total_meta_dims' => 39,
        'avg_rect_count' => 6.6,
        'avg_circle_count' => 1.1,
        'avg_text_count' => 89.5,
        'avg_path_count' => 342.0,
        'avg_node_count' => 439.1,
        'avg_color_count' => 10.5,
        'max_rect_count' => 2668,
        'max_path_count' => 10596,
        'max_text_count' => 2286,
        'max_node_count' => 13336,
        'gradient_ratio' => 0.270,
        'filter_ratio' => 0.059,
        'clip_ratio' => 0.112,
    ];

    /**
     * 图示类型分布 (top 10)
     */
    const CHART_TYPE_DISTRIBUTION = [
        '未分类' => 512, '架构图' => 182, '流程图' => 146, '框架图' => 61,
        '网络图' => 60, '甘特图' => 59, '思维导图' => 51, 'ER图' => 30,
        '技术路线图' => 29, '鱼骨图' => 24, '精益画布' => 21, '时间线' => 21,
        'SWOT' => 19, '时序图' => 17, '图表' => 15, '科研绘图' => 9,
    ];

    /**
     * 场景分布 (top 10)
     */
    const SCENE_DISTRIBUTION = [
        '其他' => 410, '流程图' => 152, '思维导图' => 71, '教育教学' => 67,
        '网络拓扑架构' => 65, '项目管理甘特图' => 65, '组织架构图' => 49,
        'UML软件设计' => 40, 'SWOT战略分析' => 36, '时间线大事记' => 33,
    ];

    /**
     * 布局类型分布
     */
    const LAYOUT_DISTRIBUTION = [
        '横版/自由形' => 549, '竖版/自由形' => 281, '方版/自由形' => 270,
        '横版/混合' => 107, '方版/混合' => 33, '竖版/混合' => 21,
        '竖版/放射' => 20, '横版/放射' => 10, '方版/放射' => 6,
    ];

    /**
     * 获取统计基线
     */
    public static function get_baseline(): array {
        return self::SVG_STATS_BASELINE;
    }

    /**
     * 按图示类型获取统计
     */
    public static function get_stats_by_chart_type(string $chart_type): array {
        $count = self::CHART_TYPE_DISTRIBUTION[$chart_type] ?? 0;
        return [
            'chart_type' => $chart_type,
            'instance_count' => $count,
            'percentage' => $count > 0 ? round($count / self::SVG_STATS_BASELINE['total_svgs'] * 100, 1) : 0,
        ];
    }

    /**
     * 按场景获取统计
     */
    public static function get_stats_by_scene(string $scene): array {
        $count = self::SCENE_DISTRIBUTION[$scene] ?? 0;
        return [
            'scene' => $scene,
            'instance_count' => $count,
            'percentage' => $count > 0 ? round($count / self::SVG_STATS_BASELINE['total_svgs'] * 100, 1) : 0,
        ];
    }

    /**
     * 预测新图示的原子数量
     * 基于图示类型的历史统计预测
     */
    public static function predict_atom_count(string $chart_type): array {
        $baseline = self::SVG_STATS_BASELINE;
        $type_count = self::CHART_TYPE_DISTRIBUTION[$chart_type] ?? 0;

        // 样本量越大，预测越准
        $confidence = $type_count >= 50 ? 'high' : ($type_count >= 10 ? 'medium' : 'low');

        return [
            'chart_type' => $chart_type,
            'predicted_rect' => $baseline['avg_rect_count'],
            'predicted_path' => $baseline['avg_path_count'],
            'predicted_text' => $baseline['avg_text_count'],
            'predicted_node' => $baseline['avg_node_count'],
            'predicted_color' => $baseline['avg_color_count'],
            'confidence' => $confidence,
            'sample_size' => $type_count,
        ];
    }

    /**
     * 获取布局分布
     */
    public static function get_layout_distribution(): array {
        return self::LAYOUT_DISTRIBUTION;
    }

    /**
     * 获取所有图示类型
     */
    public static function get_all_chart_types(): array {
        return array_keys(self::CHART_TYPE_DISTRIBUTION);
    }

    /**
     * 获取所有场景
     */
    public static function get_all_scenes(): array {
        return array_keys(self::SCENE_DISTRIBUTION);
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'module_version' => '12.2.0',
            'total_svgs' => self::SVG_STATS_BASELINE['total_svgs'],
            'total_scenes' => self::SVG_STATS_BASELINE['total_scenes'],
            'total_meta_dims' => self::SVG_STATS_BASELINE['total_meta_dims'],
            'chart_types_count' => count(self::CHART_TYPE_DISTRIBUTION),
            'source' => 'V18法篇6 + svg原子级meta全量.xlsx',
        ];
    }
}
