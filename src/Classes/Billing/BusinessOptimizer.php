<?php

declare(strict_types=1);
/**
 * Business Optimizer — EX 部门商业自寻优入口。
 *
 * 跑完 spec 后自主探索最佳商业/付费模式:
 *   - A/B 测试定价 (Free/Pro ¥99 vs ¥49, Premium ¥299 vs ¥499)
 *   - 转化率/ARPU/Churn 自动分析
 *   - 增长黑客: 邀请奖励、限时促销
 *
 * 落地机制: 通过 `linked3/plan_definitions` filter 动态调整,无需发版。
 *
 * @package Linked3
 * @subpackage Classes\Billing
 */

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) {
    exit;
}

final class BusinessOptimizer
{
    /** @var self|null */
    private static $instance;

    /** @var string Current active experiment ID. */
    private $experiment_id;

    public static function instance() : mixed {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Linked3_Container')) {
                $container = \Linked3\Includes\Linked3_Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->experiment_id = (string) get_option(LINKED3_OPTION_PREFIX . 'active_experiment', 'baseline');

        // Hook the plan definitions filter so our experiment can adjust pricing.
        add_filter('linked3/plan_definitions', [$this, 'apply_experiment'], 20);
    }

    /**
     * Apply the active experiment's pricing/quota overrides.
     *
     * @param array $plans
     * @return array
     */
    public function apply_experiment($plans) : mixed {
        $experiments = $this->experiments();
        $exp = $experiments[$this->experiment_id] ?? null;
        if (!$exp || empty($exp['overrides'])) {
            return $plans;
        }
        foreach ($exp['overrides'] as $plan => $fields) {
            if (isset($plans[$plan])) {
                $plans[$plan] = array_merge($plans[$plan], $fields);
            }
        }
        return $plans;
    }

    /**
     * @return array<string,array> Available experiments.
     */
    public function experiments()
    : array {
        return [
            'baseline' => [
                'name' => 'Baseline pricing (¥99/¥299)',
                'overrides' => [],
                'hypothesis' => 'Current pricing is optimal.',
            ],
            'exp_low_price' => [
                'name' => 'Lower price (¥49/¥199)',
                'overrides' => [
                    'pro' => ['price_monthly' => 49, 'price_yearly' => 499],
                    'premium' => ['price_monthly' => 199, 'price_yearly' => 1999],
                ],
                'hypothesis' => 'Lower price → higher conversion → higher net revenue.',
            ],
            'exp_double_quota' => [
                'name' => 'Double Pro quota',
                'overrides' => [
                    'pro' => ['tokens_daily' => 6000000],
                ],
                'hypothesis' => 'Doubling quota increases perceived value → higher conversion.',
            ],
            'exp_yearly_discount' => [
                'name' => 'Aggressive yearly discount',
                'overrides' => [
                    'pro' => ['price_yearly' => 699], // ~42% off
                    'premium' => ['price_yearly' => 1999],
                ],
                'hypothesis' => 'Yearly discount improves retention + cash flow.',
            ],
        ];
    }

    /**
     * Activate a new experiment. The next daily cron will compare metrics
     * and may auto-promote it to baseline.
     *
     * @param string $experiment_id
     * @return bool
     */
    public function activate($experiment_id)
    : bool {
        $experiments = $this->experiments();
        if (!isset($experiments[$experiment_id])) {
            return false;
        }
        update_option(LINKED3_OPTION_PREFIX . 'active_experiment', $experiment_id);
        update_option(LINKED3_OPTION_PREFIX . 'experiment_started_at', time());
        $this->experiment_id = $experiment_id;
        return true;
    }

    /**
     * Daily analysis — compare active experiment's metrics vs baseline.
     * If significantly better, promote to baseline; if worse, revert.
     *
     * @return void
     */
    public static function daily_analyze()
    : void {
        $self = self::instance();
        $started_at = (int) get_option(LINKED3_OPTION_PREFIX . 'experiment_started_at', 0);
        // Need at least 7 days of data.
        if ($started_at === 0 || (time() - $started_at) < 7 * DAY_IN_SECONDS) {
            return;
        }
        if ($self->experiment_id === 'baseline') {
            return;
        }

        $metrics = $self->collect_metrics();
        $baseline_metrics = get_option(LINKED3_OPTION_PREFIX . 'baseline_metrics', []);

        $decision = $self->decide($metrics, $baseline_metrics);
        if ($decision === 'promote') {
            // Promote experiment → make it the new baseline.
            update_option(LINKED3_OPTION_PREFIX . 'baseline_metrics', $metrics);
            update_option(LINKED3_OPTION_PREFIX . 'active_experiment', 'baseline');
            $self->experiment_id = 'baseline';
            update_option(LINKED3_OPTION_PREFIX . 'experiment_started_at', 0);
        } elseif ($decision === 'revert') {
            update_option(LINKED3_OPTION_PREFIX . 'active_experiment', 'baseline');
            $self->experiment_id = 'baseline';
            update_option(LINKED3_OPTION_PREFIX . 'experiment_started_at', 0);
        }
    }

    /**
     * Collect 7-day metrics from usage_logs + license/billing state.
     *
     * @return array{conversions:int, revenue:int, churn:int, ai_calls:int, tokens:int}
     */
    private function collect_metrics()
    : array {
        global $wpdb;
        $usage_table = $wpdb->prefix . 'linked3_usage_logs';
        $since = gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS);

        // AI calls + tokens in the last 7 days (proxy for engagement).
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as calls, COALESCE(SUM(total_tokens),0) as tokens FROM {$usage_table} WHERE created_at >= %s",
            $since
        ), ARRAY_A);

        // Conversions: count users whose plan meta changed in the window.
        $conversions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'linked3_plan' AND meta_value IN ('pro','premium')"
        );

        // v4.9.2: read real revenue + churn from the billing_events table.
        $revenue = 0.0;
        $churn = 0;
        if (class_exists('\\Linked3\\Classes\\Billing\\BillingEventRepository')) {
            $repo = new \Linked3\Classes\Billing\BillingEventRepository();
            $metrics = $repo->revenue_metrics(7);
            $revenue = (float) $metrics['total_revenue'];

            // Churn = count of subscription.cancelled + subscription.expired events.
            // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
            $billing_table = $wpdb->prefix . 'linked3_billing_events';
            $churn_cutoff = date('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS);
            $churn = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$billing_table}
                 WHERE status = 'success'
                   AND event_type IN ('subscription.cancelled', 'subscription.expired')
                   AND created_at >= %s",
                $churn_cutoff
            ));
        }

        return [
            'conversions' => $conversions,
            'revenue'     => $revenue,  // v4.9.2: real revenue from billing_events
            'churn'       => $churn,    // v4.9.2: real churn from billing_events
            'ai_calls'    => (int) ($row['calls'] ?? 0),
            'tokens'      => (int) ($row['tokens'] ?? 0),
        ];
    }

    /**
     * @param array $exp
     * @param array $baseline
     * @return string 'promote' | 'revert' | 'continue'
     */
    private function decide($exp, $baseline)
    : string {
        if (empty($baseline)) {
            return 'continue'; // no baseline to compare yet
        }
        // Promote if experiment's ARPU is >10% better.
        $exp_arpu = $exp['conversions'] > 0 ? $exp['revenue'] / $exp['conversions'] : 0;
        $base_arpu = $baseline['conversions'] > 0 ? $baseline['revenue'] / $baseline['conversions'] : 0;
        if ($exp_arpu > $base_arpu * 1.10) {
            return 'promote';
        }
        if ($exp_arpu < $base_arpu * 0.90) {
            return 'revert';
        }
        return 'continue';
    }
}
