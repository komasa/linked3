<?php

declare(strict_types=1);
/**
 * ScaleBootstrap — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class ScaleBootstrap {
    private static bool $booted = false;

    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $container = linked3_container();
        $container->set('vector.incremental', fn() => VectorIncremental::instance());
        $container->set('i18n.manager', fn() => I18nManager::instance());
        $container->set('multisite.publisher', fn() => MultiSitePublisher::instance());
        $container->set('batch.engine', fn() => BatchEngine::instance());
        $container->set('performance.cache', fn() => PerformanceCache::instance());

        linked3_dispatch('linked3.scale.boot', ['version' => LINKED3_VERSION]);
    }
}
