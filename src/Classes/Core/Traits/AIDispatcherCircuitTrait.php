<?php

declare(strict_types=1);
/**
 * AIDispatcherCircuitTrait — Provider-level circuit breaker.
 *
 * @package Linked3\Core
 */

namespace Linked3\Classes\Core\Traits;

if (!defined('ABSPATH')) exit;

trait AIDispatcherCircuitTrait
{
    /**
     * @param string $slug
     * @return bool
     */
    private function is_circuit_open(string $slug): bool
    {
        return (int) get_transient('linked3_pcb_' . $slug) >= self::CIRCUIT_THRESHOLD;
    }

    /**
     * @param string $slug
     * @return void
     */
    private function reset_circuit(string $slug): void {
        delete_transient('linked3_pcb_' . $slug);
    }

    /**
     * @param string $slug
     * @param string $message
     * @return void
     */
    private function record_failure(string $slug, string $message): void {
        $key = 'linked3_pcb_' . $slug;
        // Read-modify-write race is acceptable here: transient TTL is short,
        // and a stale read at worst delays the circuit opening by one cycle.
        $n = (int) get_transient($key) + 1;
        set_transient($key, $n, 5 * MINUTE_IN_SECONDS);
    }
}
