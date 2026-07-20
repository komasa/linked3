<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Relationship_Encoder — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramRelationshipEncoder {
    private array $codes = [
        'support'    => ['symbol' => '→', 'name_zh' => '支撑', 'meaning' => 'A支撑B'],
        'influence'  => ['symbol' => '~>', 'name_zh' => '影响', 'meaning' => 'A影响B'],
        'causal'     => ['symbol' => '<->', 'name_zh' => '因果', 'meaning' => 'A与B互为因果'],
        'strong'     => ['symbol' => '━', 'name_zh' => '强连接', 'meaning' => 'A强连接B'],
        'weak'       => ['symbol' => '┄', 'name_zh' => '弱连接', 'meaning' => 'A弱连接B'],
        'feedback'   => ['symbol' => '~>', 'name_zh' => '反馈', 'meaning' => 'A反馈B'],
    ];

    public function encode(string $type, string $from, string $to, string $desc = ''): array {
        $code = $this->codes[$type] ?? $this->codes['support'];
        return [
            'from' => $from,
            'to' => $to,
            'code' => $code['symbol'],
            'type' => $type,
            'name' => $code['name_zh'],
            'desc' => $desc,
            'rendered' => "{$from} {$code['symbol']} {$to}" . ($desc ? ": {$desc}" : ''),
        ];
    }

    public function getCodes(): array { return $this->codes; }
}

// =================================================================
// v6.3.0.7: 6级认知标注
// =================================================================
