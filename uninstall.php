<?php
/**
 * WordPress standard uninstall entry point.
 *
 * This file runs when WP deletes the plugin WITHOUT loading the main plugin
 * file — it's the safest uninstall path. Delegates to Uninstaller class.
 *
 * @package Linked3
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delegate to Uninstaller class (manually require since main file isn't loaded)
require_once __DIR__ . '/src/Includes/Uninstaller.php';
\Linked3\Includes\Uninstaller::uninstall();
