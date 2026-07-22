<?php

declare(strict_types=1);
/**
 * AddonInterface — contract for all Linked3 addon modules.
 *
 * Every addon must implement this interface so that AddonManager can
 * uniformly register, activate, and execute them.
 *
 * @package Linked3\Classes\Addons
 */

namespace Linked3\Classes\Addons;

if (!defined('ABSPATH')) {
    exit;
}

interface AddonInterface
{
    /**
     * Returns the unique machine identifier for this addon.
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Whether this addon is mandatory (cannot be deactivated).
     *
     * @return bool
     */
    public function is_required(): bool;

    /**
     * Whether this addon is currently active (user toggled on).
     *
     * @return bool
     */
    public function is_active(): bool;

    /**
     * Execute the addon's runtime logic (hooks, filters, etc.).
     *
     * @return void
     */
    public function execute(): void;
}
