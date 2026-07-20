<?php

declare(strict_types=1);
/**
 * DiagramSeedLock — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramSeedLock {
    const LEVEL_CRITICAL  = 'critical';  // 100%
    const LEVEL_IMPORTANT = 'important'; // >95%
    const LEVEL_FLEXIBLE  = 'flexible';  // >80%

    public function checkLock(array $seed, array $generated): array {
        $results = [];
        foreach (['critical', 'important', 'flexible'] as $level) {
            $items = $seed['priority'][$level] ?? [];
            $found = 0;
            foreach ($items as $item) {
                if (str_contains(json_encode($generated, JSON_UNESCAPED_UNICODE), $item)) $found++;
            }
            $rate = count($items) > 0 ? $found / count($items) : 1;
            $threshold = $level === 'critical' ? 1.0 : ($level === 'important' ? 0.95 : 0.80);
            $results[$level] = [
                'total' => count($items),
                'found' => $found,
                'rate' => round($rate * 100, 1) . '%',
                'threshold' => ($threshold * 100) . '%',
                'passed' => $rate >= $threshold,
            ];
        }
        return $results;
    }
}

// =================================================================
// v6.4.0.5: Seed校验5维度
// =================================================================
