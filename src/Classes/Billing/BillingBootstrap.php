<?php

declare(strict_types=1);
/**
 * Linked3_Billing_Bootstrap — extracted from StripeGateway.php during PSR-4 migration.
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
        $container->set('billing.payment', fn() => Linked3_Payment_Manager::instance());
        $container->set('billing.subscription', fn() => SubscriptionManager_V2::instance());
        $container->set('billing.quota', fn() => Linked3_Quota_Interceptor::instance());
        $container->set('billing.invoice', fn() => Linked3_Invoice_Manager::instance());
        $container->set('billing.referral', fn() => Linked3_Referral_Manager::instance());

        linked3_dispatch('linked3.billing.boot', ['version' => LINKED3_VERSION]);
    }
}
