<?php

declare(strict_types=1);
/**
 * PaymentGatewayInterface — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing
 */

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

interface PaymentGatewayInterface {
    public function createCharge(float $amount, string $currency, array $metadata = []): array;
    public function verifyCallback(array $data): bool;
    public function refund(string $chargeId, float $amount): array;
    public function getName(): string;
}
