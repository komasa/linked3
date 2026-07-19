<?php

declare(strict_types=1);
/**
 * Linked3_Security_Bootstrap — extracted from RateLimiterV2.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Security

namespace Linked3\Classes\Security;

if (!defined('ABSPATH')) exit;

class Linked3_Security_Bootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('security.validator', fn() => SecurityValidator::instance());
        $container->set('security.rate_limiter', fn() => RateLimiterV2::instance());
        $container->set('security.async_queue', fn() => AsyncQueue::instance());
        $container->set('security.audit', fn() => Linked3_Audit_Logger::instance());

        // 监听安全违规
        linked3_subscribe('linked3.security.violation', function(Linked3_Event $evt) {
            linked3_container()->get('logger')->warning('Security violation', $evt->getPayload());
        });

        // 监听速率限制
        linked3_subscribe('linked3.rate_limited.exceeded', function(Linked3_Event $evt) {
            linked3_container()->get('logger')->warning('Rate limited', $evt->getPayload());
        });

        linked3_dispatch('linked3.security.boot', ['version' => LINKED3_VERSION]);
    }
}
