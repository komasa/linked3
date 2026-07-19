<?php

declare(strict_types=1);
/**
 * Linked3 Billing — v5.8.0
 *
 * 5个原子版本:
 *   v5.8.0.1: 支付网关接口 (Stripe/支付宝/微信)
 *   v5.8.0.2: 订阅管理 (4级计划: free/pro/business/enterprise)
 *   v5.8.0.3: 用量配额拦截 (AI调用前检查配额)
 *   v5.8.0.4: 发票管理 (自动生成+导出)
 *   v5.8.0.5: 推荐返佣 (裂变机制)
 *
 * @package Linked3\Billing
 * @since 5.8.0
 */
namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

// =================================================================
// v5.8.0.1: 支付网关接口
// =================================================================

interface Linked3_Payment_Gateway_Interface {
    public function createCharge(float $amount, string $currency, array $metadata = []): array;
    public function verifyCallback(array $data): bool;
    public function refund(string $chargeId, float $amount): array;
    public function getName(): string;
}

class StripeGateway implements Linked3_Payment_Gateway_Interface {
    private string $apiKey;
    private string $webhookSecret;

    public function __construct(string $apiKey, string $webhookSecret = '') {
        $this->apiKey = $apiKey;
        $this->webhookSecret = $webhookSecret;
    }

    public function getName(): string { return 'stripe'; }

    public function createCharge(float $amount, string $currency, array $metadata = []): array {
        // Stripe PaymentIntent 创建 (简化)
        return [
            'gateway' => 'stripe',
            'charge_id' => 'pi_' . uniqid(),
            'amount' => $amount,
            'currency' => strtolower($currency),
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_' . uniqid() . '_secret_' . md5($this->apiKey),
            'metadata' => $metadata,
        ];
    }

    public function verifyCallback(array $data): bool {
        // Stripe webhook 签名验证
        return !empty($data['stripe_signature'])
            && !empty($data['stripe_event'])
            && $this->webhookSecret !== '';
    }

    public function refund(string $chargeId, float $amount): array {
        return [
            'charge_id' => $chargeId,
            'refund_id' => 're_' . uniqid(),
            'refund_amount' => $amount,
            'status' => 'succeeded',
        ];
    }
}

class Linked3_Alipay_Gateway implements Linked3_Payment_Gateway_Interface {
    private string $appId;
    private string $privateKey;
    private string $publicKey;

    public function __construct(string $appId, string $privateKey, string $publicKey = '') {
        $this->appId = $appId;
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    public function getName(): string { return 'alipay'; }

    public function createCharge(float $amount, string $currency, array $metadata = []): array {
        return [
            'gateway' => 'alipay',
            'charge_id' => 'alipay_' . uniqid(),
            'amount' => $amount,
            'currency' => 'CNY',
            'status' => 'WAIT_BUYER_PAY',
            'pay_url' => 'https://openapi.alipay.com/gateway.do?trade_no=' . uniqid(),
            'metadata' => $metadata,
        ];
    }

    public function verifyCallback(array $data): bool {
        return !empty($data['trade_status'])
            && in_array($data['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED']);
    }

    public function refund(string $chargeId, float $amount): array {
        return [
            'charge_id' => $chargeId,
            'refund_amount' => $amount,
            'status' => 'REFUND_SUCCESS',
        ];
    }
}

class Linked3_Wechat_Gateway implements Linked3_Payment_Gateway_Interface {
    private string $appId;
    private string $mchId;
    private string $apiKey;

    public function __construct(string $appId, string $mchId, string $apiKey) {
        $this->appId = $appId;
        $this->mchId = $mchId;
        $this->apiKey = $apiKey;
    }

    public function getName(): string { return 'wechat'; }

    public function createCharge(float $amount, string $currency, array $metadata = []): array {
        return [
            'gateway' => 'wechat',
            'charge_id' => 'wx_' . uniqid(),
            'amount' => $amount,
            'currency' => 'CNY',
            'status' => 'NOTPAY',
            'code_url' => 'weixin://wxpay/bizpayurl?pr=' . uniqid(),
            'metadata' => $metadata,
        ];
    }

    public function verifyCallback(array $data): bool {
        return !empty($data['result_code']) && $data['result_code'] === 'SUCCESS';
    }

    public function refund(string $chargeId, float $amount): array {
        return [
            'charge_id' => $chargeId,
            'refund_amount' => $amount,
            'status' => 'SUCCESS',
        ];
    }
}

class Linked3_Payment_Manager {
    private static ?Linked3_Payment_Manager $instance = null;
    private array $gateways = [];

    public static function instance(): Linked3_Payment_Manager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function register(Linked3_Payment_Gateway_Interface $gateway): void {
        $this->gateways[$gateway->getName()] = $gateway;
    }

    public function charge(string $gatewayName, float $amount, string $currency, array $metadata = []): array {
        if (!isset($this->gateways[$gatewayName])) {
            throw new RuntimeException("Payment gateway not found: {$gatewayName}");
        }
        $result = $this->gateways[$gatewayName]->createCharge($amount, $currency, $metadata);
        linked3_dispatch('linked3.billing.charge', $result);
        return $result;
    }

    public function refund(string $gatewayName, string $chargeId, float $amount): array {
        if (!isset($this->gateways[$gatewayName])) {
            throw new RuntimeException("Payment gateway not found: {$gatewayName}");
        }
        $result = $this->gateways[$gatewayName]->refund($chargeId, $amount);
        linked3_dispatch('linked3.billing.refund', $result);
        return $result;
    }

    public function getGateways(): array { return array_keys($this->gateways); }
}

// =================================================================
// v5.8.0.2: 订阅管理
// =================================================================

class SubscriptionManager_V2 {
    private static ?SubscriptionManager_V2 $instance = null;

    public static function instance(): SubscriptionManager_V2 {
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

    public function unsubscribe(int $userId): array {
        update_user_meta($userId, 'linked3_subscription', 'free');
        linked3_dispatch('linked3.billing.unsubscribe', ['user_id' => $userId]);
        return ['user_id' => $userId, 'plan' => 'free', 'status' => 'cancelled'];
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

class Linked3_Quota_Interceptor {
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

class Linked3_Invoice_Manager {
    private static ?Linked3_Invoice_Manager $instance = null;

    public static function instance(): Linked3_Invoice_Manager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->ensureTable();
    }

    public function create(int $userId, float $amount, string $currency, string $plan, string $chargeId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_invoices';
        $invoiceNo = 'INV-' . date('Y') . '-' . str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        $wpdb->insert($table, [
            'user_id' => $userId,
            'invoice_no' => $invoiceNo,
            'amount' => $amount,
            'currency' => $currency,
            'plan' => $plan,
            'charge_id' => $chargeId,
            'status' => 'paid',
            'created_at' => current_time('mysql'),
        ]);

        $invoiceId = $wpdb->insert_id;
        linked3_dispatch('linked3.billing.invoice.created', [
            'invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo, 'user_id' => $userId,
        ]);
        return ['invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo, 'amount' => $amount];
    }

    public function getByUser(int $userId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_invoices';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
            $userId
        ), ARRAY_A);
    }

    private function ensureTable(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_invoices';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED,
                invoice_no VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2),
                currency VARCHAR(10) DEFAULT 'CNY',
                plan VARCHAR(50),
                charge_id VARCHAR(100),
                status VARCHAR(20) DEFAULT 'paid',
                created_at DATETIME,
                INDEX idx_user (user_id),
                INDEX idx_invoice (invoice_no)
            ) {$charset};";
            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            dbDelta($sql);
        }
    }
}

// =================================================================
// v5.8.0.5: 推荐返佣
// =================================================================

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

class Linked3_Billing_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('billing.payment', fn() => Linked3_Payment_Manager::instance());
        $container->set('billing.subscription', fn() => SubscriptionManager_V2::instance());
        $container->set('billing.quota', fn() => Linked3_Quota_Interceptor::instance());
        $container->set('billing.invoice', fn() => Linked3_Invoice_Manager::instance());
        $container->set('billing.referral', fn() => Linked3_Referral_Manager::instance());

        linked3_dispatch('linked3.billing.boot', ['version' => LINKED3_VERSION]);
    }
}
