<?php

declare(strict_types=1);
/**
 * Key Rotator — round-robin across multiple API keys with fault eviction.
 *
 * Migrated from linked v2.9.6's zdycustom multi-key logic, but hardened:
 *   - Round-robin via a persisted cursor (transient, per-provider)
 *   - A key that returns 401/403/429 is marked unhealthy (transient, 10 min)
 *   - Unhealthy keys are skipped until their cool-down expires
 *   - If ALL keys are unhealthy, the least-recently-failed key is returned
 *     with a `degraded` flag so the caller can log/bill accordingly
 *
 * @package Linked3
 * @subpackage Classes\Core\Providers
 */

namespace Linked3\Classes\Core\Providers;

if (!defined('ABSPATH')) {
    exit;
}

final class KeyRotator
{
    const COOL_DOWN_SECS = 600; // 10 minutes

    /**
     * Pick the next healthy key for the given provider + key pool.
     *
     * @param string   $provider_slug
     * @param string[] $keys
     * @return array{key:string, index:int, degraded:bool}
     */
    public function pick(string $provider_slug, array $keys) : mixed {
        $keys = array_values(array_filter($keys, static function ($k) {
            return is_string($k) && $k !== '';
        }));
        if (empty($keys)) {
            return ['key' => '', 'index' => -1, 'degraded' => true];
        }
        if (count($keys) === 1) {
            $degraded = $this->is_unhealthy($provider_slug, 0);
            return ['key' => $keys[0], 'index' => 0, 'degraded' => $degraded];
        }

        $cursor = $this->cursor($provider_slug);
        $healthy = null;
        $least_failed = null;
        $least_failed_ts = PHP_INT_MAX;

        for ($i = 0; $i < count($keys); $i++) {
            $idx = ($cursor + $i) % count($keys);
            if (!$this->is_unhealthy($provider_slug, $idx)) {
                $healthy = $idx;
                break;
            }
            $ts = $this->failure_ts($provider_slug, $idx);
            if ($ts < $least_failed_ts) {
                $least_failed_ts = $ts;
                $least_failed = $idx;
            }
        }

        $chosen = $healthy ?? $least_failed ?? 0;
        // Advance cursor past the chosen key for next call.
        $this->set_cursor($provider_slug, ($chosen + 1) % count($keys));

        return [
            'key'      => $keys[$chosen],
            'index'    => $chosen,
            'degraded' => $healthy === null,
        ];
    }

    /**
     * Mark a key as failed (called by the AI Dispatcher on 401/403/429).
     *
     * @param string $provider_slug
     * @param int    $index
     * @return void
     */
    public function mark_failed(string $provider_slug, int $index)
    : void {
        set_transient(
            $this->health_key($provider_slug, $index),
            ['failed_at' => time()],
            self::COOL_DOWN_SECS
        );
    }

    /**
     * @param string $provider_slug
     * @param int    $index
     * @return bool
     */
    public function is_unhealthy(string $provider_slug, int $index) : mixed     {
        return (bool) get_transient($this->health_key($provider_slug, $index));
    }

    /**
     * @param string $provider_slug
     * @return int
     */
    private function cursor(string $provider_slug) : mixed {
        return (int) get_transient('linked3_kc_' . $provider_slug);
    }

    /**
     * @param string $provider_slug
     * @param int    $val
     * @return void
     */
    private function set_cursor(string $provider_slug, int $val)
    : void {
        set_transient('linked3_kc_' . $provider_slug, $val, DAY_IN_SECONDS);
    }

    /**
     * @param string $provider_slug
     * @param int    $index
     * @return string
     */
    private function health_key(string $provider_slug, int $index)
    : string {
        return 'linked3_kh_' . $provider_slug . '_' . $index;
    }

    /**
     * @param string $provider_slug
     * @param int    $index
     * @return int
     */
    private function failure_ts(string $provider_slug, int $index) : mixed     {
        $v = get_transient($this->health_key($provider_slug, $index));
        return is_array($v) && isset($v['failed_at']) ? (int) $v['failed_at'] : 0;
    }
}
