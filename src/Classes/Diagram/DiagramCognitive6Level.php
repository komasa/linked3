<?php

declare(strict_types=1);
/**
 * DiagramCognitive6Level — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramCognitive6Level {
    private array $levels = [
        'R'  => ['name_zh' => __('记忆', 'linked3'), 'name_en' => 'Remember', 'desc' => __('识别/回忆', 'linked3')],
        'U'  => ['name_zh' => __('理解', 'linked3'), 'name_en' => 'Understand', 'desc' => __('解释/概括', 'linked3')],
        'A'  => ['name_zh' => __('应用', 'linked3'), 'name_en' => 'Apply', 'desc' => __('执行/实施', 'linked3')],
        'An' => ['name_zh' => __('分析', 'linked3'), 'name_en' => 'Analyze', 'desc' => __('分解/比较', 'linked3')],
        'E'  => ['name_zh' => __('评价', 'linked3'), 'name_en' => 'Evaluate', 'desc' => __('判断/批判', 'linked3')],
        'C'  => ['name_zh' => __('创造', 'linked3'), 'name_en' => 'Create', 'desc' => __('设计/生成', 'linked3')],
    ];

    private array $bandDefaults = [
        1 => 'R',  // Band1: 基础底座 → 记忆
        2 => 'A',  // Band2: 执行层 → 应用
        3 => 'An', // Band3: 框架层 → 分析
        4 => 'E',  // Band4: 结果层 → 评价
    ];

}

// =================================================================
// v6.3.0.8: 4档信息密度
// =================================================================
