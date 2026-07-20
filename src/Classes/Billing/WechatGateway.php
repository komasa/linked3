<?php

declare(strict_types=1);
/**
 * Linked3_Wechat_Gateway — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class WechatGateway implements Linked3_Payment_Gateway_Interface {
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
