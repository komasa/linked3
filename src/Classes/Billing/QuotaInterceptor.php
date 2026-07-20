<?php

declare(strict_types=1);
/**
 * Linked3_Quota_Interceptor — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class QuotaInterceptor {
    private static ?Linked3_Quota_Interceptor $instance = null;

    public static function instance(): Linked3_Quota_Interceptor {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 检查用户是否可以消耗 token (AI 调用前)。
     */
    public function check(int $userId, int $tokensNeeded = 1): array {
        $subMgr = SubscriptionManager_V2::instance();
        $plan = $subMgr->getPlan($userId);
        $planInfo = $subMgr->getPlanInfo($plan);
        $quota = $planInfo['quota'];

        // 无限配额
        if ($quota === -1) {
            return ['allowed' => true, 'plan' => $plan, 'quota' => 'unlimited'];
        }

        $meter = TokenMeter::instance();
        $used = $meter->getMonthlyUsage($userId);
        $remaining = $quota - $used;

        if ($remaining < $tokensNeeded) {
            linked3_dispatch('linked3.billing.quota.exceeded', [
                'user_id' => $userId, 'plan' => $plan, 'used' => $used, 'quota' => $quota,
            ]);
            return [
                'allowed' => false,
                'reason' => 'quota_exceeded',
                'plan' => $plan,
                'used' => $used,
                'quota' => $quota,
                'remaining' => $remaining,
                'upgrade_url' => admin_url('admin.php?page=linked3-dashboard&tab=license'),
            ];
        }

        return ['allowed' => true, 'plan' => $plan, 'remaining' => $remaining];
    }

    /**
     * 在 AI Dispatcher 调用前拦截。
     */
    public function gate(int $userId, int $estimatedTokens = 1000): void {
        $check = $this->check($userId, $estimatedTokens);
        if (!$check['allowed']) {
            throw new RuntimeException(
                sprintf(
                    /* translators: 1: plan name, 2: used tokens, 3: quota, 4: upgrade URL */
                    __('配额已用尽 (计划: %1$s, 已用: %2$d/%3$d)。请升级计划: %4$s', 'linked3'),
                    $check['plan'],
                    (int) ($check['used'] ?? 0),
                    (int) ($check['quota'] ?? 0),
                    ($check['upgrade_url'] ?? '')
                )
            );
        }
    }
}

// =================================================================
// v5.8.0.4: 发票管理
// =================================================================
