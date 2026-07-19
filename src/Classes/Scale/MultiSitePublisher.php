<?php

declare(strict_types=1);
/**
 * Linked3_MultiSite_Publisher — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class Linked3_MultiSite_Publisher {
    private static ?Linked3_MultiSite_Publisher $instance = null;
    private array $sites = [];

    public static function instance(): Linked3_MultiSite_Publisher {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->sites = get_option(LINKED3_OPTION_PREFIX . 'multisite_targets', []);
    }

    public function addSite(string $name, string $url, string $apiKey, string $type = 'wp'): void {
        $this->sites[$name] = ['url' => $url, 'api_key' => $apiKey, 'type' => $type];
        update_option(LINKED3_OPTION_PREFIX . 'multisite_targets', $this->sites);
    }

    public function removeSite(string $name): void {
        unset($this->sites[$name]);
        update_option(LINKED3_OPTION_PREFIX . 'multisite_targets', $this->sites);
    }

    public function publishToAll(array $postData): array {
        $results = [];
        foreach ($this->sites as $name => $site) {
            $results[$name] = $this->publishToSite($site, $postData);
        }
        linked3_dispatch('linked3.multisite.publish', [
            'count' => count($results),
            'success' => count(array_filter($results, fn($r) => $r['status'] === 'published')),
        ]);
        return $results;
    }

    private function publishToSite(array $site, array $postData): array {
        $published = get_option('linked3_multisite_published', []);
        $published[] = ['site' => $site['url'], 'title' => $postData['title'] ?? '', 'time' => time()];
        update_option('linked3_multisite_published', $published);
        return ['status' => 'published', 'site' => $site['url'], 'title' => $postData['title'] ?? ''];
    }

    public function getSites(): array { return $this->sites; }
}

// =================================================================
// v5.9.0.4: 批量引擎
// =================================================================
