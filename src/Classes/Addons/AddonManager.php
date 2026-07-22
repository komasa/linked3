<?php

declare(strict_types=1);
/**
 * Addon system — modular extensions with IsRequired/IsActive/Executor pattern.
 *
 * Built-in addons:
 *   - consent-compliance: GDPR/CCPA cookie consent
 *   - ip-anonymization:  GDPR IP anonymization
 *   - ollama:            local model provider
 *
 * @package Linked3
 * @subpackage Classes\Addons
 */

namespace Linked3\Classes\Addons;

if (!defined('ABSPATH')) {
    exit;
}


final class AddonManager
{
    private static $instance;
    private $addons = [];

    public static function instance() : static
    {
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
    public static function instance_without_container() : static
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    public function register(AddonInterface $addon) : void
    {
        $this->addons[$addon->slug()] = $addon;
    }

    public function init_all() : void
    {
        foreach ($this->addons as $slug => $addon) {
            if ($addon->is_active() || $addon->is_required()) {
                try {
                    $addon->execute();
                } catch (\Throwable $e) {
                    if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
                        \Linked3\Includes\Log\Logger::instance()->error('general', "Addon {$slug} failed: " . $e->getMessage());
                    }
                }
            }
        }
    }

    public function all() : array
    {
        $out = [];
        foreach ($this->addons as $slug => $a) {
            $out[] = ['slug' => $slug, 'required' => $a->is_required(), 'active' => $a->is_active()];
        }
        return $out;
    }
}

// Built-in: IP Anonymization (GDPR).


// Built-in: Consent Compliance (GDPR/CCPA cookie banner).

