<?php

declare(strict_types=1);
/**
 * Linked3_Referral_Manager — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class Linked3_Referral_Manager {
    private static ?Linked3_Referral_Manager $instance = null;
    private float $commissionRate = 0.20; // 20% 返佣

    public static function instance(): Linked3_Referral_Manager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->ensureTable();
    }

    /**
     * 生成用户推荐码。
     */
    public function getReferralCode(int $userId): string {
        $code = get_user_meta($userId, 'linked3_referral_code', true);
        if (!$code) {
            $code = 'L3' . strtoupper(substr(md5($userId . time()), 0, 8));
            update_user_meta($userId, 'linked3_referral_code', $code);
        }
        return $code;
    }

    /**
     * 绑定推荐关系 (被推荐人首次付费时)。
     */
    public function bindReferral(int $referredUserId, string $referralCode): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_referrals';

        // 查找推荐人
        $referrer = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'linked3_referral_code' AND meta_value = %s",
            $referralCode
        ));
        if (!$referrer) return false;

        // 记录推荐关系
        $wpdb->insert($table, [
            'referrer_id' => $referrer,
            'referred_id' => $referredUserId,
            'referral_code' => $referralCode,
            'status' => 'bound',
            'bound_at' => current_time('mysql'),
        ]);

        linked3_dispatch('linked3.billing.referral.bound', [
            'referrer_id' => $referrer, 'referred_id' => $referredUserId,
        ]);
        return true;
    }

    /**
     * 计算返佣 (被推荐人付费时)。
     */
    public function calculateCommission(int $referredUserId, float $paymentAmount): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_referrals';

        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE referred_id = %d AND status = 'bound'",
            $referredUserId
        ), ARRAY_A);

        if (!$referral) return ['commission' => 0, 'referrer_id' => 0];

        $commission = round($paymentAmount * $this->commissionRate, 2);

        // 更新返佣金额
        $wpdb->update($table, [
            'total_payments' => ($referral['total_payments'] ?? 0) + $paymentAmount,
            'total_commission' => ($referral['total_commission'] ?? 0) + $commission,
            'status' => 'earning',
        ], ['id' => $referral['id']]);

        linked3_dispatch('linked3.billing.commission.earned', [
            'referrer_id' => $referral['referrer_id'],
            'amount' => $commission,
        ]);

        return ['commission' => $commission, 'referrer_id' => $referral['referrer_id']];
    }

    /**
     * 获取用户返佣统计。
     */
    public function getStats(int $userId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_referrals';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as referrals, COALESCE(SUM(total_commission),0) as commission
             FROM {$table} WHERE referrer_id = %d",
            $userId
        ), ARRAY_A);
        return [
            'total_referrals' => (int) ($row['referrals'] ?? 0),
            'total_commission' => (float) ($row['commission'] ?? 0),
            'code' => $this->getReferralCode($userId),
        ];
    }

    private function ensureTable(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_referrals';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                referrer_id BIGINT UNSIGNED,
                referred_id BIGINT UNSIGNED,
                referral_code VARCHAR(50),
                status VARCHAR(20) DEFAULT 'bound',
                total_payments DECIMAL(10,2) DEFAULT 0,
                total_commission DECIMAL(10,2) DEFAULT 0,
                bound_at DATETIME,
                INDEX idx_referrer (referrer_id),
                INDEX idx_referred (referred_id)
            ) {$charset};";
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            dbDelta($sql);
        }
    }
}

// =================================================================
// v5.8.0: Billing Bootstrap
// =================================================================
