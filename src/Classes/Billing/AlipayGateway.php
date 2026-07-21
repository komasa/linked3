<?php

declare(strict_types=1);
/**
 * AlipayGateway — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing
 */

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class AlipayGateway implements PaymentGatewayInterface {
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

    public function refund(string $chargeId, float $amount): array {
        return [
            'charge_id' => $chargeId,
            'refund_amount' => $amount,
            'status' => 'REFUND_SUCCESS',
        ];
    }
}
