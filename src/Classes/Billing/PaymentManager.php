<?php

declare(strict_types=1);
/**
 * PaymentManager — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing
 */

namespace Linked3\Classes\Billing;

use Linked3\Includes\EventBus;

if (!defined('ABSPATH')) exit;

class PaymentManager {
    private static ?PaymentManager $instance = null;
    private array $gateways = [];

    public static function instance(): PaymentManager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function register(PaymentGatewayInterface $gateway): void {
        $this->gateways[$gateway->getName()] = $gateway;
    }

    public function refund(string $gatewayName, string $chargeId, float $amount): array {
        if (!isset($this->gateways[$gatewayName])) {
            throw new RuntimeException("Payment gateway not found: {$gatewayName}");
        }
        $result = $this->gateways[$gatewayName]->refund($chargeId, $amount);
        EventBus::dispatch('linked3.billing.refund', $result);
        return $result;
    }

}

// =================================================================
// v5.8.0.2: 订阅管理
// =================================================================
