<?php

declare(strict_types=1);
/**
 * AIDispatcherBillingTrait — Usage logging + cost estimation.
 *
 * @package Linked3\Core
 */

namespace Linked3\Classes\Core\Traits;

if (!defined('ABSPATH')) exit;

trait AIDispatcherBillingTrait
{
    /**
     * Insert a usage log row (billing + quota source of truth).
     *
     * @param array $row
     * @return void
     */
    private function log_usage(array $row): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_usage_logs';

        $defaults = [
            'user_id'           => 0,
            'session_id'        => '',
            'module'            => 'general',
            'provider'          => '',
            'model'             => '',
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
            'cost_usd'          => 0,
            'status'            => 'ok',
            'error_code'        => '',
        ];
        $row = array_merge($defaults, $row);
        $cost = $this->estimate_cost_usd($row['provider'], $row['model'], $row['prompt_tokens'], $row['completion_tokens']);
        $row['cost_usd'] = $cost;

        // phpcs:disable WordPress.DB -- column names are constants.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (user_id, session_id, module, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_usd, status, error_code) VALUES (%d, %s, %s, %s, %s, %d, %d, %d, %f, %s, %s)",
            $row['user_id'], $row['session_id'], $row['module'], $row['provider'], $row['model'], $row['prompt_tokens'], $row['completion_tokens'], $row['total_tokens'], $cost, $row['status'], $row['error_code']
        ));
        // phpcs:enable
    }

    /**
     * Rough cost estimator — refine with live pricing in v0.1.10 admin.
     *
     * @param string $provider
     * @param string $model
     * @param int    $prompt
     * @param int    $completion
     * @return float
     */
    private function estimate_cost_usd(string $provider, string $model, int $prompt, int $completion) : mixed {
        // Rates per 1K tokens (rough, as of 2024). Replace with live config.
        $rates = [
            'openai'   => ['in' => 0.005, 'out' => 0.015],
            'deepseek' => ['in' => 0.00014, 'out' => 0.00028],
            'kimi'     => ['in' => 0.0017, 'out' => 0.0017],
            'qwen'     => ['in' => 0.0007, 'out' => 0.0028],
            'doubao'   => ['in' => 0.0008, 'out' => 0.002],
        ];
        $r = $rates[$provider] ?? ['in' => 0.002, 'out' => 0.006];
        return round(($prompt / 1000) * $r['in'] + ($completion / 1000) * $r['out'], 6);
    }
}
