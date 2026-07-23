<?php

declare(strict_types=1);
/**
 * Composite Meta Lever Interface — v20.4-fix17 复合元杠杆接口.
 *
 * 复合杠杆编排多个基础杠杆，形成完整的部门工作流。
 * 每个复合杠杆定义自己的部门编制、SLA契约和演化循环。
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

interface CompositeLeverInterface
{
    /**
     * 复合杠杆唯一标识.
     * @return string e.g. 'deai_5d'
     */
    public function id(): string;

    /**
     * 复合杠杆显示名称.
     * @return string e.g. '去AI味五部门'
     */
    public function label(): string;

    /**
     * 复合杠杆描述.
     * @return string
     */
    public function description(): string;

    /**
     * 复合杠杆级别: 'advanced' 或 'composite'.
     * @return string
     */
    public function level(): string;

    /**
     * 编排的基础杠杆ID列表 (按执行顺序).
     * @return array e.g. ['meta_essence', 'meta_reverse', 'meta_critique']
     */
    public function orchestrated_levers(): array;

    /**
     * 部门编制定义.
     * 返回每个部门的名称、使命、KPI和对应的基础杠杆。
     * @return array
     */
    public function departments(): array;

    /**
     * 完整的system_prompt (包含部门编制+SLA+演化循环).
     * @return string
     */
    public function system_prompt(): string;

    /**
     * 适用场景标签.
     * @return array
     */
    public function scene_tags(): array;
}
