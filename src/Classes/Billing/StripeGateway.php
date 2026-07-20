<?php

declare(strict_types=1);
/**
 * Stripe Payment Gateway.
 *
 * @package Linked3\Classes\Billing
 * @since 5.8.0
 */

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class StripeGateway implements PaymentGatewayInterface {
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

    public function refund(string $chargeId, float $amount): array {
        return [
            'charge_id' => $chargeId,
            'refund_id' => 're_' . uniqid(),
            'refund_amount' => $amount,
            'status' => 'succeeded',
        ];
    }
}
