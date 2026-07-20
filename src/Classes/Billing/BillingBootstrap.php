<?php

declare(strict_types=1);
/**
 * BillingBootstrap — extracted from StripeGateway.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Billing

namespace Linked3\Classes\Billing;

if (!defined('ABSPATH')) exit;

class BillingBootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('billing.payment', fn() => PaymentManager::instance());
        $container->set('billing.subscription', fn() => SubscriptionManager_V2::instance());
        $container->set('billing.quota', fn() => QuotaInterceptor::instance());
        $container->set('billing.invoice', fn() => InvoiceManager::instance());
        $container->set('billing.referral', fn() => ReferralManager::instance());

        linked3_dispatch('linked3.billing.boot', ['version' => LINKED3_VERSION]);
    }
}
