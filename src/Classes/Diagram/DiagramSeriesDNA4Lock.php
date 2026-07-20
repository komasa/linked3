<?php

declare(strict_types=1);
/**
 * DiagramSeriesDNA4Lock — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramSeriesDNA4Lock {
    private array $locks = [
        'layer1' => ['name' => 'META签名锁', 'field' => 'signature', 'lock_level' => 'Critical 100%'],
        'layer2' => ['name' => '角标系统锁', 'field' => 'badge_system', 'lock_level' => 'Critical 100%'],
        'layer3' => ['name' => '徽章色系锁', 'field' => 'badge_colors', 'lock_level' => 'Critical 100%'],
        'layer4' => ['name' => '排版骨架锁', 'field' => 'layout_skeleton', 'lock_level' => 'Important >95%'],
    ];

    public function applyLocks(array $seriesConfig): array {
        $result = [];
        foreach ($this->locks as $layer => $lock) {
            $result[$layer] = array_merge($lock, [
                'value' => $seriesConfig[$lock['field']] ?? null,
                'locked' => true,
            ]);
        }
        return $result;
    }

    public function verifyLocks(array $lockedConfig, array $generated): array {
        $results = [];
        foreach ($this->locks as $layer => $lock) {
            $field = $lock['field'];
            $expected = $lockedConfig[$layer]['value'] ?? '';
            $actual = $generated[$field] ?? '';
            $match = $expected === $actual;
            $results[$layer] = [
                'name' => $lock['name'],
                'expected' => $expected,
                'actual' => $actual,
                'locked' => $match,
            ];
        }
        $allLocked = count(array_filter($results, fn($r) => $r['locked'])) === count($results);
        return ['all_locked' => $allLocked, 'layers' => $results];
    }

    public function getLocks(): array { return $this->locks; }
}

// =================================================================
// v6.4.0.7: 四层断裂诊断
// =================================================================
