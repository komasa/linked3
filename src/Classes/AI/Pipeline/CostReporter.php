<?php

declare(strict_types=1);
/**
 * CostReporter — extracted from StreamOutput.php during PSR-4 migration.
 *
 * @package Linked3\Classes\AI\Pipeline

 */

namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class CostReporter {
    public static function getReport(string $period = 'monthly', ?int $userId = null): array {
        $meter = TokenMeter::instance();
        $date = $period === 'monthly' ? current_time('Y-m') : current_time('Y-m-d');

        $report = [
            'period' => $period,
            'date' => $date,
            'total_tokens' => 0,
            'total_cost' => 0,
            'by_provider' => [],
            'by_user' => [],
            'by_module' => [],
        ];

        $usage = get_option(LINKED3_OPTION_PREFIX . 'token_usage', []);
        $entries = $usage[$period][$date] ?? [];

        foreach ($entries as $e) {
            if ($userId !== null && $e['user_id'] !== $userId) continue;
            $report['total_tokens'] += $e['total_tokens'];

            $p = $e['provider'];
            if (!isset($report['by_provider'][$p])) {
                $report['by_provider'][$p] = ['tokens' => 0, 'calls' => 0];
            }
            $report['by_provider'][$p]['tokens'] += $e['total_tokens'];
            $report['by_provider'][$p]['calls']++;

            $u = $e['user_id'];
            if (!isset($report['by_user'][$u])) {
                $report['by_user'][$u] = ['tokens' => 0, 'calls' => 0];
            }
            $report['by_user'][$u]['tokens'] += $e['total_tokens'];
            $report['by_user'][$u]['calls']++;

            $m = $e['module'];
            if (!isset($report['by_module'][$m])) {
                $report['by_module'][$m] = ['tokens' => 0, 'calls' => 0];
            }
            $report['by_module'][$m]['tokens'] += $e['total_tokens'];
            $report['by_module'][$m]['calls']++;
        }

        return $report;
    }

    public static function formatCost(float $cost): string {
        if ($cost < 0.01) return '$' . number_format($cost * 1000, 2) . 'm'; // milli
        if ($cost < 1) return '$' . number_format($cost, 4);
        return '$' . number_format($cost, 2);
    }
}
