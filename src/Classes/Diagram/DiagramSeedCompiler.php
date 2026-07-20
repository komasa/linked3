<?php

declare(strict_types=1);
/**
 * DiagramSeedCompiler — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramSeedCompiler {
    /**
     * JSON Seed → MD格式。
     */
    public function toMD(array $seed): string {
        $md = "# CharacterSeed: {$seed['id']}\n\n";
        $md .= "## VisualDNA\n";
        foreach ($seed['visual_dna'] ?? [] as $k => $v) {
            $md .= ucfirst($k) . ": {$v}\n";
        }
        $md .= "\n## Priority\n";
        $md .= "Critical: " . implode('+', $seed['priority']['critical'] ?? []) . "\n";
        $md .= "Important: " . implode('+', $seed['priority']['important'] ?? []) . "\n";
        $md .= "Flexible: " . implode('+', $seed['priority']['flexible'] ?? []) . "\n";
        return $md;
    }
}
