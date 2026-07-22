<?php

declare(strict_types=1);
/**
 * Deactivator. Clears crons, flushes rewrite rules.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class Deactivator
{
    /**
     * Runs on register_deactivation_hook.
     * Clears all scheduled cron events and flushes rewrite rules.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Clear all scheduled cron events.
        $cron_hooks = [
            'linked3_daily_health_check',
            'linked3_token_reset',
            'linked3_sse_cache_cleanup',
            'linked3_log_prune',
            'linked3_license_heartbeat',
            'linked3_subscription_check',
            'linked3_business_optimize',
            'linked3_autogpt_run',
        ];
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            while ($timestamp !== false) {
                wp_unschedule_event($timestamp, $hook);
                $timestamp = wp_next_scheduled($hook);
            }
        }

        // Flush rewrite rules so plugin-specific endpoints are removed.
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }
    }
}
