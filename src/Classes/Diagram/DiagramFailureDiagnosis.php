<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Failure_Diagnosis — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Failure_Diagnosis {
    public function diagnose(array $generated, array $seriesConfig): array {
        $seriesDNA = new Linked3_Diagram_SeriesDNA_4Lock();
        $lockResult = $seriesDNA->verifyLocks($seriesConfig, $generated);

        $fractures = [];
        foreach ($lockResult['layers'] as $layer => $info) {
            if (!$info['locked']) {
                $fractures[] = [
                    'layer' => $layer,
                    'name' => $info['name'],
                    'fracture_type' => 'layer_mismatch',
                    'expected' => $info['expected'],
                    'actual' => $info['actual'],
                    'fix' => "将{$info['name']}修复为: {$info['expected']}",
                ];
            }
        }

        return [
            'fracture_count' => count($fractures),
            'fractures' => $fractures,
            'all_passed' => empty($fractures),
            'lock_result' => $lockResult,
        ];
    }
}

// =================================================================
// v6.4.0.8: Loop×角色校验7步
// =================================================================
