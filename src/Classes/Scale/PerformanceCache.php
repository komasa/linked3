<?php

declare(strict_types=1);
/**
 * PerformanceCache — extracted from VectorIncremental.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Scale
 */

namespace Linked3\Classes\Scale;

if (!defined('ABSPATH')) exit;

class PerformanceCache {
    private static ?PerformanceCache $instance = null;
    private array $cache = [];
    private int $ttl = 3600;

    public static function instance(): PerformanceCache {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function get(string $key) : mixed {
        if (!isset($this->cache[$key])) return null;
        if ((time() - $this->cache[$key]['time']) > $this->cache[$key]['ttl']) {
            unset($this->cache[$key]);
            return null;
        }
        return $this->cache[$key]['data'];
    }

    public function set(string $key, $data, int $ttl = 0): void {
        $this->cache[$key] = ['data' => $data, 'time' => time(), 'ttl' => $ttl ?: $this->ttl];
    }

    public function delete(string $key): void { unset($this->cache[$key]); }
    public function getStats(): array {
        return ['items' => count($this->cache), 'memory' => strlen(serialize($this->cache))];
    }
}

// =================================================================
// v5.9.0: Scale Bootstrap
// =================================================================
