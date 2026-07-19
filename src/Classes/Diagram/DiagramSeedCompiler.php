<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Seed_Compiler — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Seed_Compiler {
    /**
     * MD格式 → JSON Seed。
     *
     * 输入 MD:
     * # CharacterSeed: flower_girl_v1
     * ## VisualDNA
     * Face: 鹅蛋脸+杏眼
     * Body: 6.5头身+165cm
     * ## Priority
     * Critical: 圆脸+豆豆眼
     * Important: 粉腮红
     */
    public function compileMD(string $md): array {
        $lines = explode("\n", $md);
        $result = ['id' => '', 'visual_dna' => [], 'personality_dna' => [], 'priority' => ['critical' => [], 'important' => [], 'flexible' => []]];
        $currentSection = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^# CharacterSeed:\s*(.+)/', $line, $m)) {
                $result['id'] = $m[1];
            } elseif (preg_match('/^## VisualDNA/', $line)) {
                $currentSection = 'visual';
            } elseif (preg_match('/^## PersonalityDNA/', $line)) {
                $currentSection = 'personality';
            } elseif (preg_match('/^## Priority/', $line)) {
                $currentSection = 'priority';
            } elseif (str_starts_with($line, 'Critical:')) {
                $items = explode('+', trim(str_replace('Critical:', '', $line)));
                $result['priority']['critical'] = array_map('trim', $items);
            } elseif (str_starts_with($line, 'Important:')) {
                $items = explode('+', trim(str_replace('Important:', '', $line)));
                $result['priority']['important'] = array_map('trim', $items);
            } elseif (str_starts_with($line, 'Flexible:')) {
                $items = explode('+', trim(str_replace('Flexible:', '', $line)));
                $result['priority']['flexible'] = array_map('trim', $items);
            } elseif (str_contains($line, ':')) {
                [$key, $val] = explode(':', $line, 2);
                $key = trim($key);
                $val = trim($val);
                $keyLower = strtolower($key);
                if ($currentSection === 'visual') {
                    $result['visual_dna'][$keyLower] = $val;
                } elseif ($currentSection === 'personality') {
                    $result['personality_dna'][$keyLower] = $val;
                }
            }
        }

        return $result;
    }

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
