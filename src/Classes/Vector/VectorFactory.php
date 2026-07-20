<?php

declare(strict_types=1);
/**
 * Vector Provider Factory — singleton-cached.
 *
 * @package Linked3
 * @subpackage Classes\Vector
 */

namespace Linked3\Classes\Vector;

use Linked3\Classes\Vector\Providers\LocalVectorProvider;



if (!defined('ABSPATH')) {
    exit;
}
final class VectorFactory
{
    private static $instance;
    private $instances = [];

    public static function instance() : mixed {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Container')) {
                $container = \Linked3\Includes\Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register('local', static function () { return new \Linked3\Classes\Vector\Providers\LocalVectorProvider(); });

        // v4.8.1: register Pinecone + Qdrant directly here (was in
        // Chat_Dependencies_Loader, which is the wrong owner — disabling
        // Chat would lose Vector cloud providers). The class_exists guards
        // make this safe even if the provider files are not loaded yet.
        if (class_exists('\\Linked3\\Classes\\Vector\\Providers\\PineconeVectorProvider')) {
            $this->register('pinecone', static function () {
                return new \Linked3\Classes\Vector\Providers\PineconeVectorProvider();
            });
        }
        if (class_exists('\\Linked3\\Classes\\Vector\\Providers\\QdrantVectorProvider')) {
            $this->register('qdrant', static function () {
                return new \Linked3\Classes\Vector\Providers\QdrantVectorProvider();
            });
        }

        // Allow Pro addons to register additional providers (OpenAI etc.).
        do_action_ref_array('linked3/register_vector_providers', [&$this]);
    }

    public function register($slug, callable $builder)
    : void {
        $this->instances[$slug . '_builder'] = $builder;
    }

    public function make($slug) : mixed {
        if (isset($this->instances[$slug])) return $this->instances[$slug];
        if (!isset($this->instances[$slug . '_builder'])) return null;
        $obj = call_user_func($this->instances[$slug . '_builder']);
        $this->instances[$slug] = $obj;
        return $obj;
    }

}
