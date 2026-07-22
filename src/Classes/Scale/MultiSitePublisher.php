<?php

declare(strict_types=1);
/**
 * MultiSitePublisher — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale
 */

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class MultiSitePublisher {
    private static ?MultiSitePublisher $instance = null;
    private array $sites = [];

    public static function instance(): MultiSitePublisher {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->sites = get_option(LINKED3_OPTION_PREFIX . 'multisite_targets', []);
    }

    private function publishToSite(array $site, array $postData): array {
        $published = get_option('linked3_multisite_published', []);
        $published[] = ['site' => $site['url'], 'title' => $postData['title'] ?? '', 'time' => time()];
        update_option('linked3_multisite_published', $published);
        return ['status' => 'published', 'site' => $site['url'], 'title' => $postData['title'] ?? ''];
    }

}

// =================================================================
// v5.9.0.4: 批量引擎
// =================================================================
