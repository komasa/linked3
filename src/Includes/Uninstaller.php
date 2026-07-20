<?php

declare(strict_types=1);
/**
 * Uninstaller. Called when user clicks "Delete" in Plugins screen.
 * Removes all tables + options + crons. No soft-delete.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

// This file is loaded directly by WordPress on uninstall — constants
// we expect may not exist, so re-declare defensively.
if (!defined('LINKED3_OPTION_PREFIX')) {
    define('LINKED3_OPTION_PREFIX', 'linked3_');
}
if (!defined('LINKED3_DB_VERSION_OPTION')) {
    define('LINKED3_DB_VERSION_OPTION', 'linked3_db_version');
}

final class Uninstaller
{
}

// WordPress calls uninstall.php or register_uninstall_hook. We expose both
// via the static method, and let the main plugin file register the hook.
