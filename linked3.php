<?php
/**
 * Plugin Name:       Linked3 AI
 * Plugin URI:        https://linked3.com
 * Description:       Commercial self-evolution AI engine for WordPress — multi-model AI, SEO, content automation, SaaS billing.
 * Version:           29.2.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Linked3 Group
 * Author URI:        https://linked3.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       linked3
 * Domain Path:       /languages
 *
 * @package Linked3
 */

if (!defined('ABSPATH')) {
    exit;
}

// NOTE: This is the v29.2.0 entry marker. Full source tree (839 files) must be
// pushed via local git. See docs/FULL_PUSH.md for instructions.
// v30 MVP layers are already present under src/Classes/.

define('LINKED3_VERSION', '29.2.0');
define('LINKED3_DIR', plugin_dir_path(__FILE__));
define('LINKED3_URL', plugin_dir_url(__FILE__));

// Autoload + bootstrap would load here in full package.
