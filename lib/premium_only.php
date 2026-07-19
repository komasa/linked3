<?php
/**
 * Premium-only loader. Loaded unconditionally by the main plugin file, but
 * internally checks plan before requiring any Pro class.
 *
 * Pro classes live under lib/ and are NOT autoloaded by the PSR-4 loader
 * (which only covers src/). This file manually requires them when the site
 * has a Pro/Premium license.
 *
 * @package Linked3
 */

if (!defined('ABSPATH')) {
    exit;
}

// Guard against double-load.
if (defined('LINKED3_PREMIUM_LOADED')) {
    return;
}
define('LINKED3_PREMIUM_LOADED', true);

add_action('plugins_loaded', static function () {
    // Wait for License Service to be available.
    if (!class_exists('\\Linked3\\Classes\\License\\Linked3_License_Service')) {
        return;
    }
    $plan = \Linked3\Classes\License\Linked3_License_Service::instance()->plan();
    if ($plan === 'free') {
        return;
    }

    // Pro is licensed — load premium classes.
    $pro_files = [
        // Reserved for v0.3.x content-writer Pro features.
        // lib/content-writer/class-linked3-pro-content-writer.php,
        // lib/chat/class-linked3-pro-chat-triggers.php,
        // lib/autogpt/class-linked3-pro-autogpt.php,
    ];

    foreach ($pro_files as $relative) {
        $path = LINKED3_DIR . 'lib/' . $relative;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // Let Pro addons self-register their providers, hooks, etc.
    do_action('linked3/pro_loaded', $plan);
}, 20);
