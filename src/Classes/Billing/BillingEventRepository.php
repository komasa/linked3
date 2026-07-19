<?php

declare(strict_types=1);
/**
 * Billing Event Repository — audit log for billing webhooks.
 *
 * v4.9.4: stores raw webhook payloads + parsed results so billing can be
 * reconciled and audited. Used by Subscription_Manager (webhook receiver)
 * and Business_Optimizer (metrics collection).
 *
 * @package Linked3
 * @subpackage Classes\Billing
 */

namespace Linked3\Classes\Billing;

use Linked3\Includes\DB\Linked3_Base_Repository;

if (!defined('ABSPATH')) {
    exit;
}

final class BillingEventRepository extends Linked3_Base_Repository
{
    /**
     * {@inheritdoc}
     */
    protected function table_name(): string
    {
        return 'linked3_billing_events';
    }

    /**
     * {@inheritdoc}
     */
    protected function primary_key(): string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    protected function fillable(): array
    {
        return ['event_type', 'provider', 'license_key', 'user_id', 'plan',
                'amount', 'currency', 'status', 'raw_payload', 'signature'];
    }

    /**
     * Log a billing webhook event.
     *
     * @param array $data
     * @return int Inserted row ID (0 on failure).
     */
    public function log_event(array $data): int
    {
        $clean = [
            'event_type'  => sanitize_text_field($data['event_type'] ?? ''),
            'provider'    => sanitize_text_field($data['provider'] ?? ''),
            'license_key' => sanitize_text_field($data['license_key'] ?? ''),
            'user_id'     => (int) ($data['user_id'] ?? 0),
            'plan'        => sanitize_text_field($data['plan'] ?? ''),
            'amount'      => (float) ($data['amount'] ?? 0),
            'currency'    => sanitize_text_field($data['currency'] ?? 'USD'),
            'status'      => sanitize_text_field($data['status'] ?? 'pending'),
            'raw_payload' => wp_json_encode($data['raw_payload'] ?? []),
            'signature'   => sanitize_text_field($data['signature'] ?? ''),
        ];
        $id = $this->insert_row($clean);
        return $id ?? 0;
    }

    /**
     * Get recent events for a user.
     *
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public function for_user(int $user_id, int $limit = 50): array
    {
        global $wpdb;
        $table = $this->get_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);
        return $rows ?: [];
    }

    /**
     * Aggregate revenue metrics for Business_Optimizer.
     *
     * @param int $days  Lookback window in days (default 30).
     * @return array{total_revenue:float, event_count:int, by_plan:array}
     */
    public function revenue_metrics(int $days = 30): array
    {
        global $wpdb;
        $table = $this->get_table();

        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $cutoff = date('Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS);

        // Total revenue + event count.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COALESCE(SUM(amount), 0) AS total_revenue,
                COUNT(*) AS event_count
             FROM {$table}
             WHERE status = 'success'
               AND created_at >= %s",
            $cutoff
        ), ARRAY_A);

        // Revenue by plan.
        $by_plan_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT plan, COALESCE(SUM(amount), 0) AS revenue, COUNT(*) AS cnt
             FROM {$table}
             WHERE status = 'success'
               AND created_at >= %s
             GROUP BY plan",
            $cutoff
        ), ARRAY_A);

        $by_plan = [];
        if (is_array($by_plan_rows)) {
            foreach ($by_plan_rows as $r) {
                $by_plan[$r['plan']] = [
                    'revenue' => (float) $r['revenue'],
                    'count'   => (int) $r['cnt'],
                ];
            }
        }

        return [
            'total_revenue' => (float) ($row['total_revenue'] ?? 0),
            'event_count'   => (int) ($row['event_count'] ?? 0),
            'by_plan'       => $by_plan,
        ];
    }

    /**
     * Prune events older than N days. Called by cron.
     *
     * @param int $days
     * @return int Rows deleted.
     */
    public function prune_older_than(int $days): int
    {
        global $wpdb;
        $table = $this->get_table();
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $cutoff = date('Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS);
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $cutoff
        ));
    }
}
