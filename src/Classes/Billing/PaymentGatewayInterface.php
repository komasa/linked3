<?php

declare(strict_types=1);
/**
 * Linked3_Payment_Gateway_Interface — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

interface Linked3_Payment_Gateway_Interface {
    public function createCharge(float $amount, string $currency, array $metadata = []): array;
    public function verifyCallback(array $data): bool;
    public function refund(string $chargeId, float $amount): array;
    public function getName(): string;
}
