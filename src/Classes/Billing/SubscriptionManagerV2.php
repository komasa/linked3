<?php

declare(strict_types=1);
/**
 * SubscriptionManagerV2 — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing
 */

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class SubscriptionManagerV2 {
    private static ?SubscriptionManagerV2 $instance = null;

    public static function instance(): SubscriptionManagerV2 {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getPlans(): array {
        return [
            'free' => [
                'name' => __('免费版', 'linked3'), 'price' => 0, 'currency' => 'CNY',
                'quota' => 50000, 'features' => [__('基础AI写作', 'linked3'), __('关键词生成', 'linked3'), __('1站点', 'linked3')],
                'rate_limit' => ['ai_per_minute' => 5, 'ajax_per_minute' => 20],
            ],
            'pro' => [
                'name' => __('专业版', 'linked3'), 'price' => 99, 'currency' => 'CNY',
                'quota' => 500000, 'features' => [__('高级AI', 'linked3'), __('SEO套件', 'linked3'), __('自动Agent', 'linked3'), __('3站点', 'linked3')],
                'rate_limit' => ['ai_per_minute' => 20, 'ajax_per_minute' => 60],
            ],
            'business' => [
                'name' => __('商业版', 'linked3'), 'price' => 299, 'currency' => 'CNY',
                'quota' => 2000000, 'features' => [__('全功能', 'linked3'), __('多站点(10)', 'linked3'), __('API接入', 'linked3'), __('优先支持', 'linked3')],
                'rate_limit' => ['ai_per_minute' => 60, 'ajax_per_minute' => 120],
            ],
            'enterprise' => [
                'name' => __('企业版', 'linked3'), 'price' => 999, 'currency' => 'CNY',
                'quota' => -1, 'features' => [__('无限配额', 'linked3'), __('私有部署', 'linked3'), __('SLA保障', 'linked3'), __('专属客服', 'linked3'), __('无限站点', 'linked3')],
                'rate_limit' => ['ai_per_minute' => 999, 'ajax_per_minute' => 999],
            ],
        ];
    }

    public function subscribe(int $userId, string $plan, ?string $chargeId = null): array {
        $plans = $this->getPlans();
        if (!isset($plans[$plan])) throw new RuntimeException("Unknown plan: {$plan}");

        update_user_meta($userId, 'linked3_subscription', $plan);
        update_user_meta($userId, 'linked3_subscription_started', time());
        update_user_meta($userId, 'linked3_subscription_charge', $chargeId);

        linked3_dispatch('linked3.billing.subscribe', [
            'user_id' => $userId, 'plan' => $plan, 'charge_id' => $chargeId,
        ]);

        return ['user_id' => $userId, 'plan' => $plan, 'status' => 'active', 'charge_id' => $chargeId];
    }

    public function getPlan(int $userId): string {
        return get_user_meta($userId, 'linked3_subscription', true) ?: 'free';
    }

    public function getPlanInfo(string $plan): array {
        $plans = $this->getPlans();
        return $plans[$plan] ?? $plans['free'];
    }
}

// =================================================================
// v5.8.0.3: 用量配额拦截
// =================================================================
