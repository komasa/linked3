<?php
/**
 * v30 Migration Shim
 * Load early. When linked3_v30_mvp is off, zero impact.
 */
if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', function () {
    if (!get_option('linked3_v30_mvp', false)) {
        return; // full rollback
    }
    // New paths (Presenter / UseCase / Registry) are active.
    // Old AJAX endpoints and shortcodes continue to work via existing registrars.
}, 1);
