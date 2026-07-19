<?php
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

final class Linked3_Deactivator
{
    /**
     * @return void
     */
    public static function deactivate()
    : void {
        // Clear our scheduled crons. v1.0.0 FINAL-AUDIT: previous list was
        // missing 4 crons (license_heartbeat, business_optimize, autogpt_run,
        // push_log_prune) and one entry used the wrong hook name
        // (linked3_push_log_retention vs the actual linked3_push_log_prune).
        // Unscheduled crons would keep firing after deactivation, leaving
        // dead cron entries in wp_options until manually cleared.
        $crons = [
            'linked3_daily_health_check',
            'linked3_token_reset',
            'linked3_sse_cache_cleanup',
            'linked3_log_prune',
            'linked3_push_log_prune',
            'linked3_license_heartbeat',
            'linked3_subscription_check',
            'linked3_business_optimize',
            'linked3_autogpt_run',
        ];
        foreach ($crons as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        flush_rewrite_rules();
    }
}
