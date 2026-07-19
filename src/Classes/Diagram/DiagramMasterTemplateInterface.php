<?php

declare(strict_types=1);
/**
 * DiagramMasterTemplate_Interface — extracted from DiagramMasterTemplate.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

interface DiagramMasterTemplate_Interface {
    public function generate(array $config): array;
    public function validate(array $diagram): array;
    public function getSignature(): string;
}

/**
 * 知识图谱型图示主模板
 *
 * 核心结构:
 *   9:16竖版 → 4个水平Band → 每Band含模块 → 每模块含3层深度
 *   Band1: 基础底座 (1模块)
 *   Band2: 执行层 (3并列模块)
 *   Band3: 框架层 (1模块)
 *   Band4: 结果层 (1模块)
 *   Endpoint: 右下角终点图示
 *   Footer: 底部全局价值观
 */
