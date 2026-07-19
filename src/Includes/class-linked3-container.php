<?php
/**
 * Linked3 DI Container — lightweight dependency injection container.
 *
 * Introduced in v4.4.2 to break the 14-singleton coupling that has grown
 * since v0.1.0. The container supports:
 *
 *   - Factory closures (lazy instantiation — the closure runs only on the
 *     first `get()` call, then the result is cached).
 *   - Pre-built instances (set() with a concrete object).
 *   - Singleton backward-compat: `::instance()` on migrated classes
 *     delegates to `Container::get(class)` so existing call sites keep
 *     working without a sweeping rewrite.
 *
 * v4.5.3: removed `implements \Psr\Container\ContainerInterface` to
 * eliminate the PSR-11 interface load-order fragility that caused
 * repeated fatal errors in v4.5.0–v4.5.2. The container still has the
 * same `get()` and `has()` method signatures — only the formal PSR-11
 * marker is gone. If a future version needs to interoperate with
 * third-party PSR-11 consumers, the interface can be reintroduced via
 * a Composer `psr/container` dependency (which guarantees correct
 * load order).
 *
 * Conventions:
 *   - Keys are fully-qualified class names (FQCN), no aliases.
 *   - Factories receive the container as their only argument so they can
 *     resolve dependencies.
 *   - `has()` returns true for any registered factory.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Container
{
    /** @var array<string, callable> Factory closures keyed by FQCN. */
    private $factories = [];

    /** @var array<string, object> Resolved singletons keyed by FQCN. */
    private $instances = [];

    /** @var array<string, bool> Classes explicitly registered as "shared". */
    private $shared = [];

    /** @var self|null */
    private static $instance;

    /**
     * Get the process-wide container instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->register_defaults();
        }
        return self::$instance;
    }

    /**
     * Register a factory closure for a class.
     *
     * The closure is called lazily on the first `get()`, and (if $shared
     * is true) the result is cached for subsequent calls.
     *
     * @param string   $id       Fully-qualified class name.
     * @param callable $factory  Receives the container as its only arg.
     * @param bool     $shared   Whether to cache the result (default true).
     * @return self
     */
    public function set(string $id, callable $factory, bool $shared = true): self
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = $shared;
        // Clear any previously-resolved instance so re-registration takes effect.
        unset($this->instances[$id]);
        return $this;
    }

    /**
     * Register a pre-built instance.
     *
     * @param string $id
     * @param object $instance
     * @return self
     */
    public function set_instance(string $id, object $instance): self
    {
        $this->instances[$id] = $instance;
        $this->shared[$id] = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            // PSR-11: throw NotFoundExceptionInterface when the entry does
            // not exist. We do NOT auto-wire — every class must be
            // explicitly registered.
            throw new Linked3_NotFoundException(
                sprintf('No factory registered for "%s".', $id)
            );
        }

        $instance = call_user_func($this->factories[$id], $this);

        if ($this->shared[$id] ?? true) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        if (isset($this->instances[$id]) || isset($this->factories[$id])) {
            return true;
        }
        // PSR-11 allows has() to return true if get() could resolve the
        // entry. We are conservative: return true only for explicitly
        // registered entries.
        return false;
    }

    /**
     * Clear all resolved instances. Mainly useful in tests.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->instances = [];
    }

    /**
     * Register the v4.4.2/v4.4.6 default bindings: all 13 singletons that
     * have been migrated to the container.
     *
     * IMPORTANT: the factories MUST construct the instance directly via
     * `instance_without_container()` rather than calling `::instance()`,
     * otherwise we get infinite recursion (::instance() → Container::get()
     * → factory → ::instance()).
     *
     * @return void
     */
    private function register_defaults(): void
    {
        // v4.4.2 — the 3 core singletons.
        $this->set(
            \Linked3\Classes\Core\Linked3_AI_Dispatcher::class,
            static function (): \Linked3\Classes\Core\Linked3_AI_Dispatcher {
                return \Linked3\Classes\Core\Linked3_AI_Dispatcher::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Core\Linked3_Token_Manager::class,
            static function (): \Linked3\Classes\Core\Linked3_Token_Manager {
                return \Linked3\Classes\Core\Linked3_Token_Manager::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Core\Providers\Linked3_Provider_Factory::class,
            static function (): \Linked3\Classes\Core\Providers\Linked3_Provider_Factory {
                return \Linked3\Classes\Core\Providers\Linked3_Provider_Factory::instance_without_container();
            }
        );

        // v4.4.6 — the remaining 10 singletons.
        $this->set(
            \Linked3\Includes\Log\Linked3_Logger::class,
            static function (): \Linked3\Includes\Log\Linked3_Logger {
                return \Linked3\Includes\Log\Linked3_Logger::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\License\LicenseService::class,
            static function (): \Linked3\Classes\License\LicenseService {
                return \Linked3\Classes\License\LicenseService::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Chat\Linked3_Chat_Manager::class,
            static function (): \Linked3\Classes\Chat\Linked3_Chat_Manager {
                return \Linked3\Classes\Chat\Linked3_Chat_Manager::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Publish\Linked3_Publish_Manager::class,
            static function (): \Linked3\Classes\Publish\Linked3_Publish_Manager {
                return \Linked3\Classes\Publish\Linked3_Publish_Manager::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\SEO\Push\Linked3_Push_Manager::class,
            static function (): \Linked3\Classes\SEO\Push\Linked3_Push_Manager {
                return \Linked3\Classes\SEO\Push\Linked3_Push_Manager::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Distribute\Linked3_Distribute_Manager::class,
            static function (): \Linked3\Classes\Distribute\Linked3_Distribute_Manager {
                return \Linked3\Classes\Distribute\Linked3_Distribute_Manager::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Billing\BusinessOptimizer::class,
            static function (): \Linked3\Classes\Billing\BusinessOptimizer {
                return \Linked3\Classes\Billing\BusinessOptimizer::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\SEO\Schema\Linked3_Schema_Markup::class,
            static function (): \Linked3\Classes\SEO\Schema\Linked3_Schema_Markup {
                return \Linked3\Classes\SEO\Schema\Linked3_Schema_Markup::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Vector\Linked3_Vector_Factory::class,
            static function (): \Linked3\Classes\Vector\Linked3_Vector_Factory {
                return \Linked3\Classes\Vector\Linked3_Vector_Factory::instance_without_container();
            }
        );
        $this->set(
            \Linked3\Classes\Addons\AddonManager::class,
            static function (): \Linked3\Classes\Addons\AddonManager {
                return \Linked3\Classes\Addons\AddonManager::instance_without_container();
            }
        );
    }
}

/**
 * v10.7.7 Bridge: linked3_container() helper function.
 *
 * History: The original linked3_container() was defined in a dead
 * Classes/Core/Container/ file (v5.4.0 "module bus" architecture) that
 * was never loaded. v10.7.7 added this bridge; v10.7.8 physically
 * deleted the dead file. This bridge is now the canonical definition.
 *
 * @since 10.7.7
 * @return \Linked3\Includes\Linked3_Container
 */
if ( ! function_exists( 'linked3_container' ) ) {
    function linked3_container() {
        return \Linked3\Includes\Linked3_Container::instance();
    }
}
