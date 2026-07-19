<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Cognitive_6Level — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Cognitive_6Level {
    private array $levels = [
        'R'  => ['name_zh' => '记忆', 'name_en' => 'Remember', 'desc' => '识别/回忆'],
        'U'  => ['name_zh' => '理解', 'name_en' => 'Understand', 'desc' => '解释/概括'],
        'A'  => ['name_zh' => '应用', 'name_en' => 'Apply', 'desc' => '执行/实施'],
        'An' => ['name_zh' => '分析', 'name_en' => 'Analyze', 'desc' => '分解/比较'],
        'E'  => ['name_zh' => '评价', 'name_en' => 'Evaluate', 'desc' => '判断/批判'],
        'C'  => ['name_zh' => '创造', 'name_en' => 'Create', 'desc' => '设计/生成'],
    ];

    private array $bandDefaults = [
        1 => 'R',  // Band1: 基础底座 → 记忆
        2 => 'A',  // Band2: 执行层 → 应用
        3 => 'An', // Band3: 框架层 → 分析
        4 => 'E',  // Band4: 结果层 → 评价
    ];

    public function getLevel(string $code): ?array {
        return $this->levels[$code] ?? null;
    }

    public function getDefaultForBand(int $bandNum): string {
        return $this->bandDefaults[$bandNum] ?? 'R';
    }

    public function getLevels(): array { return $this->levels; }
    public function getBandDefaults(): array { return $this->bandDefaults; }
}

// =================================================================
// v6.3.0.8: 4档信息密度
// =================================================================
