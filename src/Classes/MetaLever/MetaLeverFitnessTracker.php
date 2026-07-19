<?php

declare(strict_types=1);
/**
 * Dynamic Fitness Tracker — evaluates lever effectiveness after each use.
 *
 * G3.7: When a new SKILL is generated, the fitness tracker re-evaluates
 * all levers (basic + composite) and adjusts their scores based on:
 *   - Usage frequency (how often the lever was selected)
 *   - Success rate (user accepted the lever's output)
 *   - Cross-domain transfer (lever worked well outside its primary domain)
 *   - Novelty contribution (lever contributed to unique outputs)
 *
 * Scores are stored as WordPress transients (with 30-day TTL) and
 * exposed via the registry for the selection algorithm.
 *
 * @package Linked3
 * @subpackage Classes\MetaLever
 * @since      27.4.0
 */

namespace Linked3\Classes\MetaLever;

if (!defined('ABSPATH')) {
    exit;
}

final class MetaLeverFitnessTracker
{
    const SCORES_OPTION = 'linked3_lever_fitness_scores';
    const USAGE_OPTION = 'linked3_lever_usage_stats';

    /**
     * Record a lever usage event.
     *
     * @param string $lever_id The lever that was used.
     * @param bool   $success  Whether the output was accepted by the user.
     * @param string $task_type The task type (e.g. 'xhs_generate', 'seo_article').
     * @return void
     */
    public static function record_usage(string $lever_id, bool $success, string $task_type = ''): void
    {
        $stats = (array) get_option(self::USAGE_OPTION, []);
        if (!isset($stats[$lever_id])) {
            $stats[$lever_id] = ['used' => 0, 'success' => 0, 'tasks' => []];
        }
        $stats[$lever_id]['used']++;
        if ($success) $stats[$lever_id]['success']++;
        if ($task_type && !in_array($task_type, $stats[$lever_id]['tasks'])) {
            $stats[$lever_id]['tasks'][] = $task_type;
        }
        update_option(self::USAGE_OPTION, $stats);
    }

    /**
     * Get the fitness score for a lever (0.0 - 1.0).
     *
     * @param string $lever_id
     * @return float Fitness score (default 0.5 for new levers).
     */
    public static function get_score(string $lever_id): float
    {
        $scores = (array) get_option(self::SCORES_OPTION, []);
        return $scores[$lever_id] ?? 0.5;
    }

    /**
     * Get scores for all levers.
     *
     * @return array<string, float>
     */
    public static function get_all_scores(): array
    {
        return (array) get_option(self::SCORES_OPTION, []);
    }

    /**
     * Re-calculate fitness scores for all levers based on usage stats.
     * Called after a new SKILL is generated or periodically.
     *
     * @return array Updated scores.
     */
    public static function recalculate(): array
    {
        $stats = (array) get_option(self::USAGE_OPTION, []);
        $scores = [];

        foreach ($stats as $lever_id => $data) {
            $used = max(1, $data['used']);
            $success_rate = $data['success'] / $used;
            $task_diversity = count($data['tasks']);
            $novelty_bonus = min(0.2, $task_diversity * 0.05);

            // Score = success_rate * 0.6 + usage_frequency * 0.2 + diversity * 0.2
            $usage_freq = min(1.0, $used / 10); // Normalize: 10+ uses = 1.0
            $diversity_score = min(1.0, $task_diversity / 5); // 5+ task types = 1.0

            $scores[$lever_id] = round(
                $success_rate * 0.6 + $usage_freq * 0.2 + $diversity_score * 0.2 + $novelty_bonus,
                3
            );
            // Clamp to [0, 1]
            $scores[$lever_id] = max(0.0, min(1.0, $scores[$lever_id]));
        }

        update_option(self::SCORES_OPTION, $scores);
        return $scores;
    }

    /**
     * Get a human-readable fitness report.
     *
     * @return array Top levers sorted by score.
     */
    public static function get_report(int $limit = 10): array
    {
        $scores = self::get_all_scores();
        arsort($scores);
        return array_slice($scores, 0, $limit, true);
    }

    /**
     * AJAX: Get fitness scores for admin dashboard.
     */
    public static function register_ajax(): void
    {
        add_action('wp_ajax_linked3_lever_fitness', [self::class, 'ajax_get_fitness']);
    }

    public static function ajax_get_fitness(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }
        wp_send_json_success([
            'scores' => self::get_all_scores(),
            'report' => self::get_report(20),
        ]);
    }
}

// Register AJAX endpoint
add_action('init', [MetaLeverFitnessTracker::class, 'register_ajax'], 20);
