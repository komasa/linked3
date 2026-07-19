<?php

declare(strict_types=1);
/**
 * Linked3_Payment_Manager — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

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
