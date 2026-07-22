<?php
/**
 * Linked3 Event Bus — namespaced class + global function aliases.
 *
 * History: The original linked3_dispatch/subscribe were defined in a
 * dead Classes/Core/EventBus/ file (v5.4.0 "module bus" architecture)
 * that was never loaded. v10.7.7 added this bridge; v10.7.8 physically
 * deleted the dead file. This bridge is now the canonical definition,
 * delegating to WordPress's native action/filter system.
 *
 * v27.1.0 (P11): Wrapped the two free functions in a proper namespace
 * (Linked3\Includes) and a static class EventBus. Global
 * function aliases linked3_dispatch() / linked3_subscribe() are kept
 * in the global namespace block for backward compatibility with any
 * code that still calls them procedurally.
 *
 * @package Linked3
 * @since   10.7.7
 */

namespace Linked3\Includes {

use Linked3\Includes\EventBus;

    if (!defined('ABSPATH')) {
        exit;
    }

    /**
     * Static event-bus facade delegating to WordPress do_action / add_action.
     *
     * @since 27.1.0 Extracted from procedural functions-events.php (P11).
     */
    final class EventBus
    {
        /**
         * Dispatch an event to all subscribers.
         *
         * Delegates to do_action_ref_array() so existing WordPress hooks
         * continue to work. The event name is prefixed with 'linked3/'
         * to avoid collisions with core or third-party hooks.
         *
         * @since 10.7.7
         * @param string $event   Event name (e.g. 'content.generated').
         * @param mixed  ...$args Variable arguments passed to subscribers.
         * @return void
         */
        static function dispatch(string $event, ...$args): void {
            $hook = 'linked3/' . $event;
            do_action_ref_array($hook, $args);
        }

        /**
         * Subscribe a callback to an event.
         *
         * Delegates to add_action() on the 'linked3/{event}' hook.
         *
         * @since 10.7.7
         * @param string   $event    Event name (e.g. 'content.generated').
         * @param callable $callback Subscriber callback.
         * @param int      $priority Priority (default 10).
         * @param int      $args     Accepted args count (default 1).
         * @return void
         */
        static function subscribe(string $event, callable $callback, int $priority = 10, int $args = 1): void {
            $hook = 'linked3/' . $event;
            add_action($hook, $callback, $priority, $args);
        }
    }
}

/**
 * Global namespace — backward-compatible procedural aliases.
 */
namespace {

    if (!defined('ABSPATH')) {
        exit;
    }

    if (!function_exists('linked3_dispatch')) {
        /**
         * Procedural alias for EventBus::dispatch().
         *
         * @since 10.7.7
         * @deprecated 27.1.0 Use Linked3\Includes\EventBus::dispatch().
         * @param string $event   Event name.
         * @param mixed  ...$args Variable arguments.
         * @return void
         */
        function linked3_dispatch($event, ...$args): void {
            \Linked3\Includes\EventBus::dispatch($event, ...$args);
        }
    }

    if (!function_exists('linked3_subscribe')) {
        /**
         * Procedural alias for EventBus::subscribe().
         *
         * @since 10.7.7
         * @deprecated 27.1.0 Use Linked3\Includes\EventBus::subscribe().
         * @param string   $event    Event name.
         * @param callable $callback Subscriber callback.
         * @param int      $priority Priority.
         * @param int      $args     Accepted args count.
         * @return void
         */
        function linked3_subscribe($event, $callback, $priority = 10, $args = 1): void {
            \Linked3\Includes\EventBus::subscribe($event, $callback, $priority, $args);
        }
    }
}
