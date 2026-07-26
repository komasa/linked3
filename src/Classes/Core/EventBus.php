<?php
declare(strict_types=1);
namespace Linked3\Classes\Core;
if (!defined('ABSPATH')) exit;

/**
 * EventBus — v30 MVP
 * Single source for cross-cutting events (beforeFetch/afterFetch/taskCompleted)
 */
class EventBus {
    private static array $listeners = [];

    public static function on(string $event, callable $cb): void {
        self::$listeners[$event][] = $cb;
    }

    public static function dispatch(string $event, $payload = null): void {
        foreach (self::$listeners[$event] ?? [] as $cb) {
            $cb($payload);
        }
    }
}
