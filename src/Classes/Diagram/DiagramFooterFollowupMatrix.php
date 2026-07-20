<?php

declare(strict_types=1);
/**
 * DiagramFooterFollowupMatrix — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramFooterFollowupMatrix {
    private array $matrix = [
        'values'    => ['E1' => true, 'E2' => true, 'E3' => true, 'E4' => false, 'E5' => false, 'E6' => true],
        'method'    => ['E1' => true, 'E2' => true, 'E3' => true, 'E4' => true, 'E5' => true, 'E6' => false],
        'principle' => ['E1' => false, 'E2' => true, 'E3' => true, 'E4' => false, 'E5' => true, 'E6' => true],
        'formula'   => ['E1' => true, 'E2' => false, 'E3' => false, 'E4' => true, 'E5' => true, 'E6' => false],
    ];

    public function isCompatible(string $footerType, string $followupType): bool {
        return $this->matrix[$footerType][$followupType] ?? false;
    }

    public function getCompatibleFollowups(string $footerType): array {
        return array_keys(array_filter($this->matrix[$footerType] ?? [], fn($v) => $v));
    }

    public function getMatrix(): array { return $this->matrix; }
}

// =================================================================
// v6.3.0.6: 6种关系编码
// =================================================================
