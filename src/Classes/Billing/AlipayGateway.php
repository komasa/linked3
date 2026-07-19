<?php

declare(strict_types=1);
/**
 * Linked3_Alipay_Gateway — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing
 */

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

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
