<?php

declare(strict_types=1);
/**
 * Addon interface — contract for modular Linked3 extensions.
 *
 * Every addon must implement this interface so that AddonManager can
 * uniformly register, activate and execute it.
 *
 * @package Linked3\Classes\Addons
 * @since 27.0.0
 */

namespace Linked3\Classes\Addons;

if (!defined('ABSPATH')) {
    exit;
}

interface AddonInterface
{
    /**
     * Unique machine identifier for this addon.
     *
     * @return string e.g. 'consent-compliance'
     */
    public function slug(): string;

    /**
     * Whether this addon is mandatory (always loaded regardless of settings).
     *
     * @return bool
     */
    public function is_required(): bool;

    /**
     * Whether this addon is currently enabled by the site admin.
     *
     * @return bool
     */
    public function is_active(): bool;

    /**
     * Run the addon — register hooks, enqueue assets, etc.
     *
     * Called by AddonManager::init_all() only when is_active() || is_required().
     *
     * @return void
     */
    public function execute(): void;
}
