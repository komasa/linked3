<?php

declare(strict_types=1);
/**
 * Dashboard — unified admin overview: usage stats, plan, health.
 *
 * @package Linked3
 * @subpackage Classes\Dashboard
 */

namespace Linked3\Classes\Dashboard;

if (!defined('ABSPATH')) {
    exit;
}

final class Dashboard
{
    /**
     * @return array{plan:string, tokens_today:int, tokens_quota:int, ai_calls_30d:int, tasks_active:int, providers_configured:int}
     */
    public function overview()
    : array {
        global $wpdb;
        $license = \Linked3\Classes\License\LicenseService::instance();
        $plan = $license->plan();
        $user_id = get_current_user_id();

        $tokens_today = \Linked3\Classes\Core\TokenManager::instance()->used_today($user_id);
        $quota = \Linked3\Classes\License\PlanDefinitions::feature($plan, 'tokens_daily') ?: 50000;

        $usage_table = $wpdb->prefix . 'linked3_usage_logs';
        $ai_calls_30d = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$usage_table} WHERE user_id = %d AND created_at >= %s",
            $user_id, gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
        ));

        $tasks_table = $wpdb->prefix . 'linked3_tasks';
        $tasks_active = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} WHERE user_id = %d AND status = 'active'",
            $user_id
        ));

        $keys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        $providers_configured = is_array($keys) ? count(array_filter($keys)) : 0;

        // v16.0.6: V18 subsystem health check integration
        $v18_health = [];
        $v18_loaded = 0;
        $v18_total = 0;
        if (class_exists('\Linked3\Classes\Dashboard\Linked3_V18') && method_exists('\Linked3\Classes\Dashboard\Linked3_V18', 'health_check')) {
            try {
                $v18_health = \Linked3_V18::health_check();
                $v18_total = count($v18_health);
                foreach ($v18_health as $mod) {
                    if (!empty($mod['loaded'])) $v18_loaded++;
                }
            } catch (\Throwable $e) {
                // V18 health check failed — record but don't break the dashboard
                if (function_exists('error_log')) {
                    error_log('[Linked3] V18 health check failed: ' . $e->getMessage());
                }
            }
        }

        // v16.0.6: Activation warning (from v16.0.1 FIX 1)
        $activation_warning = get_option('linked3_activation_warning', null);

        return [
            'plan' => $plan,
            'tokens_today' => $tokens_today,
            'tokens_quota' => $quota,
            'tokens_remaining' => max(0, $quota - $tokens_today),
            'ai_calls_30d' => $ai_calls_30d,
            'tasks_active' => $tasks_active,
            'providers_configured' => $providers_configured,
            // v16.0.6: V18 integration status
            'v18_loaded' => $v18_loaded,
            'v18_total' => $v18_total,
            'v18_health' => $v18_health,
            'activation_warning' => $activation_warning,
        ];
    }

    /**
     * @param int $days
     * @return array<int,array{date:string, calls:int, tokens:int}>
     */
    public function usage_chart(int $days = 30) : mixed {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_usage_logs';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as d, COUNT(*) as calls, SUM(total_tokens) as tokens
             FROM {$table}
             WHERE user_id = %d AND created_at >= %s
             GROUP BY d ORDER BY d ASC",
            get_current_user_id(),
            gmdate('Y-m-d', time() - $days * DAY_IN_SECONDS)
        ), ARRAY_A);
        return $rows ?: [];
    }
}
