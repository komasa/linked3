<?php

declare(strict_types=1);
/**
 * DiagramVisualFrequency — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramVisualFrequency {
    private array $frequencies = [
        'HF' => ['name_zh' => __('高频', 'linked3'), 'name_en' => 'High Frequency', 'desc' => __('快速切换/动画密集', 'linked3'), 'fps' => '24fps', 'suitable_for' => __('动感/紧张/科技', 'linked3')],
        'MF' => ['name_zh' => __('中频', 'linked3'), 'name_en' => 'Medium Frequency', 'desc' => __('正常节奏/适中切换', 'linked3'), 'fps' => '12fps', 'suitable_for' => __('教学/讲解/展示', 'linked3')],
        'LF' => ['name_zh' => __('低频', 'linked3'), 'name_en' => 'Low Frequency', 'desc' => __('静态/慢速/定格', 'linked3'), 'fps' => '6fps', 'suitable_for' => __('冥想/总结/品牌', 'linked3')],
    ];

}
