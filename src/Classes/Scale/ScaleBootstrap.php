<?php

declare(strict_types=1);
/**
 * Linked3_Scale_Bootstrap — extracted from VectorIncremental.php during PSR-4 migration.
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
        $container->set('i18n.manager', fn() => Linked3_i18n_Manager::instance());
        $container->set('multisite.publisher', fn() => Linked3_MultiSite_Publisher::instance());
        $container->set('batch.engine', fn() => Linked3_Batch_Engine::instance());
        $container->set('performance.cache', fn() => Linked3_Performance_Cache::instance());

        linked3_dispatch('linked3.scale.boot', ['version' => LINKED3_VERSION]);
    }
}
