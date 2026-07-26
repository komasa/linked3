<?php

declare(strict_types=1);
/**
 * DiagramRelationshipEncoder — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramRelationshipEncoder {
    private array $codes = [
        'support'    => ['symbol' => '→', 'name_zh' => __('支撑', 'linked3'), 'meaning' => __('A支撑B', 'linked3')],
        'influence'  => ['symbol' => '~>', 'name_zh' => __('影响', 'linked3'), 'meaning' => __('A影响B', 'linked3')],
        'causal'     => ['symbol' => '<->', 'name_zh' => __('因果', 'linked3'), 'meaning' => __('A与B互为因果', 'linked3')],
        'strong'     => ['symbol' => '━', 'name_zh' => __('强连接', 'linked3'), 'meaning' => __('A强连接B', 'linked3')],
        'weak'       => ['symbol' => '┄', 'name_zh' => __('弱连接', 'linked3'), 'meaning' => __('A弱连接B', 'linked3')],
        'feedback'   => ['symbol' => '~>', 'name_zh' => __('反馈', 'linked3'), 'meaning' => __('A反馈B', 'linked3')],
    ];

}

// =================================================================
// v6.3.0.7: 6级认知标注
// =================================================================
