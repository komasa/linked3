<?php
/**
 * Plugin Name:       Linked3 AI
 * Plugin URI:        https://linked3.com
 * Description:       Commercial self-evolution AI engine for WordPress — multi-model AI, SEO, content automation, SaaS billing. v18.5 adds Book Factory (YAML-driven 6-step automated book writing). Successor to Linkreate AI v2.9.6. v20.4 fixes COS: real AI generation in EX department, real Skill content, real lever chain analysis. v27.1.0: V18→OS 重构 + Genesis/Diagram/MetaLever 模块 namespace 补全（90 文件）+ 54 个 AJAX 委托方法修复 + 超长方法拆分。
 * Version:           29.1.1
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

// ABSPATH guard — prevents direct file access from outside WordPress.
if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/lib/linked3-ultra-early-scanner.php')) {
    require_once __DIR__ . '/lib/linked3-ultra-early-scanner.php';
    linked3_ues_init(__DIR__);
}


// -----------------------------------------------------------------------------
// ── RUNTIME __CLASS__ INTERCEPTOR (v27.3.6) ─────────────────────────────────
// Problem: add_action(['__CLASS__', 'method']) at file scope resolves __CLASS__
// to empty string "", causing "class __CLASS__ not found" Fatal Error.
// This interceptor wraps add_action/add_filter to detect and fix empty class
// callbacks BEFORE WordPress tries to execute them.
// -----------------------------------------------------------------------------
if (!function_exists('_linked3_fix_class_callback')) {
    function _linked3_fix_class_callback($callback) {
        if (!is_array($callback) || count($callback) !== 2) return $callback;
        $cls = $callback[0];
        if ($cls !== '' && $cls !== '__CLASS__') return $callback;
        // Empty class — try to resolve from callback ID in backtrace
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($bt as $frame) {
            if (isset($frame['class']) && $frame['class']) {
                $callback[0] = $frame['class'];
                return $callback;
            }
            // Try to detect from calling file
            if (isset($frame['file']) && file_exists($frame['file'])) {
                $src = file_get_contents($frame['file']);
                $line = $frame['line'] ?? 0;
                // Find namespace
                $ns = '';
                if (preg_match('/^namespace\s+([\w\\]+);/m', $src, $m)) $ns = $m[1];
                // Find last class declared before this line
                $last_class = '';
                if (preg_match_all('/^((?:final\s+)?(?:abstract\s+)?class\s+(\w+))/m', $src, $cm, PREG_OFFSET_CAPTURE)) {
                    foreach ($cm[2] as $match) {
                        $cls_line = substr_count(substr($src, 0, $match[1]), "\n") + 1;
                        if ($cls_line < $line) $last_class = $match[0];
                    }
                }
                if ($last_class) {
                    $callback[0] = $ns ? $ns . '\\' . $last_class : $last_class;
                    return $callback;
                }
            }
        }
        return $callback;
    }
}

// Store original functions (they might already be defined by WP)
if (!function_exists('_linked3_orig_add_action')) {
    // Wrap add_action — but we can't override it if it's already defined.
    // Instead, register a shutdown pre-check on 'init' priority -999.
    // Actually, we CAN'T override add_action. But we CAN use a different approach:
    // Register an 'init' handler at priority -1 (before everything) that scans
    // and fixes all registered hooks.
    add_action('plugins_loaded', function () {
        global $wp_filter;
        if (!is_array($wp_filter)) return;
        $fixed = 0;
        foreach ($wp_filter as $tag => $hook_obj) {
            if (!($hook_obj instanceof WP_Hook)) continue;
            foreach ($hook_obj->callbacks as $priority => &$callbacks) {
                foreach ($callbacks as $id => &$cb) {
                    if (!is_array($cb['function']) || count($cb['function']) !== 2) continue;
                    $cls = $cb['function'][0];
                    if ($cls === '' || $cls === '__CLASS__') {
                        // Try to get real class from callback ID
                        // WP generates ID as "ClassName::method" or "spl_object_hash"
                        if (strpos($id, '::') !== false) {
                            $parts = explode('::', $id);
                            $real = $parts[0];
                            if ($real && $real !== '__CLASS__') {
                                $cb['function'][0] = $real;
                                $fixed++;
                            }
                        }
                    }
                }
            }
        }
        if ($fixed > 0 && function_exists('error_log')) {
            error_log("[linked3] Runtime fix: $fixed hooks with empty __CLASS__ callbacks repaired");
        }
    }, 1);
    
    // Also fix right before 'init' fires (priority -1 catches hooks registered during plugins_loaded)
    add_action('init', function () {
        global $wp_filter;
        if (!is_array($wp_filter)) return;
        foreach ($wp_filter as $tag => $hook_obj) {
            if (!($hook_obj instanceof WP_Hook)) continue;
            foreach ($hook_obj->callbacks as $priority => &$callbacks) {
                foreach ($callbacks as $id => &$cb) {
                    if (!is_array($cb['function']) || count($cb['function']) !== 2) continue;
                    $cls = $cb['function'][0];
                    if ($cls === '' || $cls === '__CLASS__') {
                        if (strpos($id, '::') !== false) {
                            $parts = explode('::', $id);
                            $real = $parts[0];
                            if ($real && $real !== '__CLASS__') {
                                $cb['function'][0] = $real;
                            }
                        }
                    }
                }
            }
        }
    }, 0);
}

if (file_exists(__DIR__ . '/lib/wp-early-error-handler.php')) {
    require_once __DIR__ . '/lib/wp-early-error-handler.php';
    wp_early_error_handler_init([
        'plugin_name'       => 'Linked3 AI',
        'plugin_dir'        => __DIR__,
        'main_file'         => __FILE__,
        'scan_on_load'      => true,
        'force_display'     => true,
        'check_requires'    => true,
        'check_interfaces'  => true,
    ]);
} elseif (file_exists(__DIR__ . '/lib/linked3-early-error-handler.php')) {
    require_once __DIR__ . '/lib/linked3-early-error-handler.php';
}

// -----------------------------------------------------------------------------
// Diagnostic mode — force error display so activation fatals are visible.
// (The early error handler file already does this, but we keep it here too
//  as a redundant safety net in case the early handler file is missing.)
// -----------------------------------------------------------------------------
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    @ini_set('display_errors', 1); // phpcs:ignore
    @ini_set('display_startup_errors', 1); // phpcs:ignore
    error_reporting(E_ALL); // phpcs:ignore
}

// -----------------------------------------------------------------------------
// ── FIX v16.0.1 (REVISED v26.0.1): Disable WordPress fatal error recovery
// mode for Linked3.
//
// PREVIOUS BUG: The old code wrapped the define() in
//   `if (!defined('WP_CONTENT_DIR'))`
// but WP_CONTENT_DIR is ALWAYS defined by the time plugins load (it's set in
// wp-includes/default-constants.php which runs before plugins). So the
// constant was NEVER defined and WP's generic error page kept showing.
//
// NEW FIX: Define the constant unconditionally (guarding with !defined() so
// we don't trample a site owner's wp-config.php setting). The early error
// handler file already does this, but we keep it here for visibility.
// -----------------------------------------------------------------------------
if (!defined('WP_FATAL_ERROR_HANDLER_ENABLED')) {
    define('WP_FATAL_ERROR_HANDLER_ENABLED', false);
}
if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) {
    define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
}
// Also filter at runtime (in case the constant was already set by WP).
add_filter('wp_fatal_error_handler_enabled', '__return_false', 1);

// -----------------------------------------------------------------------------
// Core constants (single source of truth)
// -----------------------------------------------------------------------------
define('LINKED3_VERSION', '29.1.1');
define('LINKED3_DB_VERSION', '3.4.0'); // v3.4.0 adds V15 tables (brand_profiles + seeds + chart_dna)
define('LINKED3_FILE', __FILE__);
define('LINKED3_DIR', plugin_dir_path(__FILE__));
define('LINKED3_URL', plugin_dir_url(__FILE__));
define('LINKED3_BASENAME', plugin_basename(__FILE__));
define('LINKED3_TEXT_DOMAIN', 'linked3');
if (!defined('LINKED3_DB_VERSION_OPTION')) {
    define('LINKED3_DB_VERSION_OPTION', 'linked3_db_version');
}
if (!defined('LINKED3_OPTION_PREFIX')) {
    define('LINKED3_OPTION_PREFIX', 'linked3_');
}

// v4.7.2: External service endpoints. Empty string = local mode (skip remote
// calls entirely). Override in wp-config.php to enable remote license/billing
// validation + update checks. This fixes P0-3/P0-5 from the v4.6.1 audit
// (fake domains caused silent HTTP failures every 12h).
if (!defined('LINKED3_LICENSE_SERVER_URL')) {
    define('LINKED3_LICENSE_SERVER_URL', ''); // e.g. 'https://license.example.com'
}
if (!defined('LINKED3_BILLING_SERVER_URL')) {
    define('LINKED3_BILLING_SERVER_URL', ''); // e.g. 'https://billing.example.com'
}
if (!defined('LINKED3_UPDATE_API_URL')) {
    define('LINKED3_UPDATE_API_URL', ''); // e.g. 'https://example.com/api/updates'
}

// -----------------------------------------------------------------------------
// Minimum PHP version guard (defensive — fails fast on legacy hosts).
// v28 PR-01: 7.4 → 8.0 (源码已使用 match 表达式, 7.4 会 fatal)
// -----------------------------------------------------------------------------
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>'
            . esc_html(sprintf(
                /* translators: %s: PHP version. */
                __('Linked3 AI 需要 PHP 8.0 或更高版本。当前运行 %s,请升级 PHP。', 'linked3'),
                PHP_VERSION
            ))
            . '</p></div>';
    });
    return;
}

// -----------------------------------------------------------------------------
// Autoloader (PSR-4). Implemented natively to avoid Composer dependency
// during the foundation phase. Will be swapped to Composer in v0.2.6.
// -----------------------------------------------------------------------------
require_once LINKED3_DIR . 'src/autoload.php';

// v27.6.12-fix: H-03 Phase 1 — BookFactory require_once → autoload
// These files are now loaded via PSR-4 autoload (composer.json: "Linked3\\": "src/")
// The old 24 require_once lines have been removed to reduce single-point-of-failure.
// If composer autoload is not available, the internal src/autoload.php handles it.

// -----------------------------------------------------------------------------

$_linked3_preload_files = [
    LINKED3_DIR . 'src/Includes/functions-events.php',
    LINKED3_DIR . 'src/Includes/Container.php',
];
foreach ($_linked3_preload_files as $_linked3_preload_file) {
    if (file_exists($_linked3_preload_file)) {
        require_once $_linked3_preload_file;
    }
}

// -----------------------------------------------------------------------------
// Early admin menu — register the top-level "Linked3" menu immediately so it
// always appears, even if the full module bootstrap (which runs on
// plugins_loaded) fails. The submenu items are added by module registrars
// later; this just guarantees the parent menu exists and is clickable.
// -----------------------------------------------------------------------------
add_action('admin_menu', static function () {
    add_menu_page(
        'Linked3',
        'Linked3',
        'manage_options',
        'linked3-dashboard',
        '__return_empty_string', // Dashboard 模块会注册真正的 render 回调
        'dashicons-superhero',
        25
    );
}, 0);

// 隐藏左侧子菜单中的"编辑/新增"等 CPT 默认子项 — moved to linked3-seed-admin.css (enqueued via wp_enqueue_style)
// v9.1.2 修复: 旧代码 display:none 全部子菜单导致 Seed DNA 菜单不可见
// v29.1.0: Inline <style> replaced by CSS file enqueue (Step 3 CSS extraction)

// v27.8.0: Register CSS files via wp_enqueue_style (best practice)
// Replaces inline <style> echo with proper enqueue for caching + dependency management
// v29.1.0: Added dashboard, forms, generators CSS (Step 3 CSS extraction)
add_action('admin_enqueue_scripts', static function ($hook) {
    // Only load on Linked3 admin pages
    if (strpos($hook, 'linked3') === false && $hook !== 'toplevel_page_linked3-dashboard') {
        return;
    }

    $css_url = plugins_url('assets/css/', LINKED3_FILE);
    $css_dir = LINKED3_DIR . 'assets/css/';

    // Core admin styles
    if (file_exists($css_dir . 'linked3-admin.css')) {
        wp_enqueue_style('linked3-admin', $css_url . 'linked3-admin.css', [], LINKED3_VERSION);
    }
    // v16.0.18: 万兴2风格设计 token
    if (file_exists($css_dir . 'linked3-wansheng2.css')) {
        wp_enqueue_style('linked3-wansheng2', $css_url . 'linked3-wansheng2.css', ['linked3-admin'], LINKED3_VERSION);
    }
    // v16.0.17: 生态面板CSS Grid自适应布局
    if (file_exists($css_dir . 'linked3-eco-layout.css')) {
        wp_enqueue_style('linked3-eco-layout', $css_url . 'linked3-eco-layout.css', ['linked3-admin'], LINKED3_VERSION);
    }
    // v12.0: Global UI Design System
    if (file_exists($css_dir . 'linked3-admin-ui.css')) {
        wp_enqueue_style('linked3-admin-ui', $css_url . 'linked3-admin-ui.css', ['linked3-admin'], LINKED3_VERSION);
    }
    // v29.1.0 Step 3: Dashboard tab CSS (tab-v18, tab-cognitive-os, eco-synergy)
    if (file_exists($css_dir . 'linked3-dashboard.css')) {
        wp_enqueue_style('linked3-dashboard', $css_url . 'linked3-dashboard.css', ['linked3-admin'], LINKED3_VERSION);
    }
    // v29.1.0 Step 3: Forms CSS (content-writer/editor)
    if (file_exists($css_dir . 'linked3-forms.css')) {
        wp_enqueue_style('linked3-forms', $css_url . 'linked3-forms.css', ['linked3-admin'], LINKED3_VERSION);
    }
    // v29.1.0 Step 3: Generators CSS (tab-genesis, tab-charts, tab-video, style-fusion-panel-v2)
    if (file_exists($css_dir . 'linked3-generators.css')) {
        wp_enqueue_style('linked3-generators', $css_url . 'linked3-generators.css', ['linked3-admin'], LINKED3_VERSION);
    }
    // v29.1.0 Step 3: Seed admin CSS (also hides CPT submenus globally)
    if (file_exists($css_dir . 'linked3-seed-admin.css')) {
        wp_enqueue_style('linked3-seed-admin', $css_url . 'linked3-seed-admin.css', ['linked3-admin'], LINKED3_VERSION);
    }
});

// v29.1.0 Step 3: SEO scorecard CSS — loaded on post-edit screens
add_action('admin_enqueue_scripts', static function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $css_url = plugins_url('assets/css/', LINKED3_FILE);
    $css_dir = LINKED3_DIR . 'assets/css/';
    if (file_exists($css_dir . 'linked3-seo-scorecard.css')) {
        wp_enqueue_style('linked3-seo-scorecard', $css_url . 'linked3-seo-scorecard.css', [], LINKED3_VERSION);
    }
});

// v29.1.0 Step 4: Register core JS files via wp_enqueue_script + wp_localize_script
// linked3-fetch.js: AJAX helper (depends on nothing, provides window.linked3Fetch)
// linked3-core.js: shared UI utilities (tab switching, modal, etc.)
add_action('admin_enqueue_scripts', static function ($hook) {
    if (strpos($hook, 'linked3') === false && $hook !== 'toplevel_page_linked3-dashboard') {
        return;
    }
    $js_url = plugins_url('admin/js/', LINKED3_FILE);
    $js_dir = LINKED3_DIR . 'admin/js/';

    // Core AJAX helper
    if (file_exists($js_dir . 'linked3-fetch.js')) {
        wp_enqueue_script('linked3-fetch', $js_url . 'linked3-fetch.js', [], LINKED3_VERSION, true);
        wp_localize_script('linked3-fetch', 'linked3_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('linked3_ajax'),
        ]);
    }
    // Core UI utilities
    if (file_exists($js_dir . 'linked3-core.js')) {
        wp_enqueue_script('linked3-core', $js_url . 'linked3-core.js', ['linked3-fetch'], LINKED3_VERSION, true);
    }

    // Tab-module JS files (enqueued conditionally based on ?tab= parameter)
    $current_tab = $_GET['tab'] ?? 'overview';
    $tab_js_map = [
        'genesis'       => 'tab-genesis.js',
        'cognitive-os'  => 'tab-cognitive-os.js',
        'v18'           => 'tab-v18.js',
        'charts'        => 'tab-charts.js',
        'video'         => 'tab-video.js',
        'writing-center'=> 'tab-writing-center.js',
        'queue'         => 'tab-queue.js',
        'create-center' => 'tab-create-center.js',
        'cloud'         => 'tab-cloud.js',
    ];
    if (isset($tab_js_map[$current_tab]) && file_exists($js_dir . $tab_js_map[$current_tab])) {
        wp_enqueue_script('linked3-tab-' . $current_tab, $js_url . $tab_js_map[$current_tab], ['linked3-core'], LINKED3_VERSION, true);
    }

    // Eco module JS files (loaded on eco-related tabs)
    $eco_tabs = ['eco-content', 'eco-synergy', 'eco-images', 'eco-summary', 'eco-xhs', 'eco-keywords', 'eco-style-dna-picker', 'eco-seo', 'eco-rewrite', 'eco-book', 'eco-templates', 'eco-title'];
    if (in_array($current_tab, $eco_tabs, true)) {
        $eco_js_files = ['eco-shared-js.js', 'eco-content.js'];
        foreach ($eco_js_files as $eco_js) {
            if (file_exists($js_dir . $eco_js)) {
                wp_enqueue_script('linked3-' . preg_replace('/\.js$/', '', $eco_js), $js_url . $eco_js, ['linked3-core'], LINKED3_VERSION, true);
            }
        }
        // Tab-specific eco JS
        $eco_tab_js = $current_tab . '.js';
        if (file_exists($js_dir . $eco_tab_js)) {
            wp_enqueue_script('linked3-' . $current_tab, $js_url . $eco_tab_js, ['linked3-core'], LINKED3_VERSION, true);
        }
    }

    // Dashboard tabs.php command palette JS
    if (file_exists($js_dir . 'tab-cmdk.js')) {
        wp_enqueue_script('linked3-cmdk', $js_url . 'tab-cmdk.js', ['linked3-core'], LINKED3_VERSION, true);
    }

    // Settings page JS
    if ($hook === 'linked3_page_linked3-settings' || $hook === 'toplevel_page_linked3-dashboard') {
        if (file_exists($js_dir . 'settings-api.js')) {
            wp_enqueue_script('linked3-settings-api', $js_url . 'settings-api.js', ['linked3-core'], LINKED3_VERSION, true);
        }
    }

    // v29.1.0 Step 4: Tab/eco module JS extracted from admin/views/dashboard/partials/
    // All files live in assets/js/ and are loaded conditionally based on ?tab= parameter.
    // Dynamic PHP variables are passed via wp_add_inline_script in each partial file
    // (since variables like $cw_mode, $current_project_id are only in scope there).
    $a_js_url  = LINKED3_URL . 'assets/js/';
    $a_js_dir  = LINKED3_DIR . 'assets/js/';
    $tab       = $_GET['tab'] ?? 'overview';

    // Static localize for files with no partial-local variables
    $tab_js_register = [
        'genesis'              => ['linked3-tab-genesis', 'linked3_genesis', ['nonce' => wp_create_nonce('linked3_genesis'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'cognitive-os'         => ['linked3-tab-cognitive-os', 'linked3_cos', ['nonce' => wp_create_nonce('linked3_cos'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'v18'                  => ['linked3-tab-v18', 'linked3_v18', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('linked3_v18')]],
        'video'                => ['linked3-tab-video', 'linked3_video', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('linked3_video'), 'publish_url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish')]],
        'charts'               => ['linked3-tab-charts', 'linked3_charts', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('linked3_charts'), 'genesis_url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual&vs_sub=genesis'), 'publish_url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish')]],
        'cloud'                => ['linked3-tab-cloud', 'linked3_cloud', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('linked3_cloud'), 'templates_url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=templates')]],
        'eco-content'          => ['linked3-eco-content', 'linked3_eco_content', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('linked3_content_writer'), 'publish_url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish'), 'edit_url' => admin_url('post.php?action=edit&post='), 'preview_url' => home_url('/?p=')]],
        'eco-book'             => ['linked3-eco-book', 'linked3_eco_book', ['ajax_url' => admin_url('admin-ajax.php'), 'factory_nonce' => wp_create_nonce('linked3_book_factory'), 'progress_nonce' => wp_create_nonce('linked3_book_progress')]],
        'eco-synergy'          => ['linked3-eco-synergy', 'linked3_eco_synergy', ['require_html' => (!empty(get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', [])['require_html']) ? 'true' : 'false'), 'ajax_url' => admin_url('admin-ajax.php'), 'content_url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content'), 'publish_url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish'), 'api_url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api'), 'draft_url' => admin_url('edit.php?post_status=draft&post_type=post'), 'nonce' => wp_create_nonce('linked3_content_writer')]],
        'eco-keywords'         => ['linked3-eco-keywords', 'linked3_eco_keywords', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('linked3_keywords'), 'csv_url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=csv'), 'synergy_url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=synergy')]],
        'eco-rewrite'          => ['linked3-eco-rewrite', 'linked3_eco_rewrite', []],
        // v29.1.0 Step 4 batch 2: remaining admin inline JS extracted
        'overview'             => ['linked3-tab-overview', 'linked3_tab_overview', []],
        'writing-center'       => ['linked3-tab-writing-center', 'linked3_tab_wc', ['nonce' => wp_create_nonce('linked3_content_writer')]],
        'create-center'        => ['linked3-tab-create-center', 'linked3_tab_cc', ['nonce' => wp_create_nonce('linked3_content_writer')]],
        'queue'                => ['linked3-tab-queue', 'linked3_tab_queue', ['nonce_q' => wp_create_nonce('linked3_queue'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'eco-templates'        => ['linked3-eco-templates', 'linked3_eco_templates', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce_tpl' => wp_create_nonce('linked3_templates'), 'local_templates' => [], 'local_template_ids' => []]],
        'eco-images'           => ['linked3-eco-images', 'linked3_eco_images', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce_img' => wp_create_nonce('linked3_images')]],
        'eco-xhs'              => ['linked3-eco-xhs', 'linked3_eco_xhs', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce_xhs' => wp_create_nonce('linked3_xhs')]],
        'eco-config-bridge'    => ['linked3-eco-config-bridge', 'linked3_eco_config_bridge', []],
        'eco-style-dna-picker' => ['linked3-eco-style-dna-picker', 'linked3_eco_sdp', []],
        'eco-summary'          => ['linked3-eco-summary', 'linked3_eco_summary', []],
        'eco-seo'              => ['linked3-eco-seo', 'linked3_eco_seo', ['nonce_seo' => wp_create_nonce('linked3_eco_seo')]],
        'eco-shared'           => ['linked3-eco-shared-js', 'linked3_eco_shared', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce_content_writer' => wp_create_nonce('linked3_content_writer')]],
        'eco-title'            => ['linked3-eco-title', 'linked3_eco_title', []],
    ];

    if (isset($tab_js_register[$tab])) {
        [$handle, $js_var, $data] = $tab_js_register[$tab];
        $js_filename = $handle . '.js';
        if (file_exists($a_js_dir . $js_filename)) {
            wp_enqueue_script($handle, $a_js_url . $js_filename, [], LINKED3_VERSION, true);
            if (!empty($data)) {
                wp_localize_script($handle, $js_var, $data);
            }
        }
    }

    // v29.1.0 Step 4 batch 2: Non-tab page JS registrations
    $page_screen = $hook;
    $page_js_register = [
        'linked3_page_linked3-settings'          => ['linked3-settings-api', 'linked3_settings_api', 'linked3-settings-api.js', ['nonce_settings' => wp_create_nonce('linked3_settings'), 'ajax_url' => admin_url('admin-ajax.php'), 'img_providers' => []]],
        'linked3_page_linked3-seo'               => ['linked3-seo-settings', 'linked3_seo_settings', 'linked3-seo-settings.js', ['nonce_enhance' => wp_create_nonce('linked3_seo_enhance'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'linked3_page_linked3-seo-push-logs'     => ['linked3-seo-push-logs', 'linked3_seo_push_logs', 'linked3-seo-push-logs.js', ['nonce' => wp_create_nonce('linked3_seo_push'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'linked3_page_linked3-seo-dashboard'     => ['linked3-seo-dashboard', 'linked3_seo_dashboard', 'linked3-seo-dashboard.js', ['nonce' => wp_create_nonce('linked3_seo'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'toplevel_page_linked3-autogpt'          => ['linked3-autogpt', 'linked3_autogpt', 'linked3-autogpt-dashboard.js', ['nonce' => wp_create_nonce('linked3_autogpt'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'toplevel_page_linked3-forms'            => ['linked3-forms', 'linked3_forms', 'linked3-forms-dashboard.js', ['nonce' => wp_create_nonce('linked3_forms'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'toplevel_page_linked3-rewriter'         => ['linked3-rewriter', 'linked3_rewriter', 'linked3-collect-rewriter.js', ['nonce' => wp_create_nonce('linked3_rewriter'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'toplevel_page_linked3-cw-editor'        => ['linked3-cw-editor', 'linked3_cw_editor', 'linked3-cw-editor.js', ['nonce' => wp_create_nonce('linked3_content_writer'), 'ajax_url' => admin_url('admin-ajax.php'), 'admin_url' => admin_url()]],
        'toplevel_page_linked3-publish-targets'  => ['linked3-publish-targets', 'linked3_publish_targets', 'linked3-publish-targets.js', ['nonce' => wp_create_nonce('linked3_publish'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'toplevel_page_linked3-distribute'       => ['linked3-distribute', 'linked3_distribute', 'linked3-distribute-dashboard.js', ['nonce' => wp_create_nonce('linked3_distribute'), 'ajax_url' => admin_url('admin-ajax.php')]],
        'toplevel_page_linked3-wc'               => ['linked3-wc', 'linked3_wc', 'linked3-wc-dashboard.js', ['nonce' => wp_create_nonce('linked3_wc'), 'ajax_url' => admin_url('admin-ajax.php')]],
    ];
    if (isset($page_js_register[$page_screen])) {
        [$phandle, $pvar, $pfile, $pdata] = $page_js_register[$page_screen];
        if (file_exists($a_js_dir . $pfile)) {
            wp_enqueue_script($phandle, $a_js_url . $pfile, [], LINKED3_VERSION, true);
            if (!empty($pdata)) {
                wp_localize_script($phandle, $pvar, $pdata);
            }
        }
    }

    // Global dashboard tabs.php JS (command palette + tab switching)
    if (file_exists($a_js_dir . 'linked3-dashboard-tabs.js')) {
        wp_enqueue_script('linked3-dashboard-tabs', $a_js_url . 'linked3-dashboard-tabs.js', [], LINKED3_VERSION, true);
        wp_localize_script('linked3-dashboard-tabs', 'linked3_dashboard_tabs', [
            'cmdk_commands' => [],
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
});

// v12.0: Add body class to all Linked3 admin pages for global CSS targeting
add_filter('admin_body_class', static function ($classes) {
    $screen = get_current_screen();
    if ($screen && (strpos($screen->id, 'linked3') !== false || $screen->id === 'toplevel_page_linked3-dashboard')) {
        $classes .= ' linked3-admin-page';
    }
    return $classes;
});

// -----------------------------------------------------------------------------
// Bootstrap. Main class does NOT own any require_once / add_action itself —
// it delegates to the three-layer orchestrator (loaded in v0.0.3).
// -----------------------------------------------------------------------------
// Pro/Freemius-style loader: always required, internally plan-gated.
require_once LINKED3_DIR . 'lib/premium_only.php';

// v19.40: 元提示词杠杆体系 — 注册表 + 接口 + 内置杠杆
require_once LINKED3_DIR . 'src/Classes/MetaLever/MetaLeverInterface.php';
require_once LINKED3_DIR . 'src/Classes/MetaLever/MetaLeverRegistry.php';
require_once LINKED3_DIR . 'src/Classes/MetaLever/MetaLeverHooksRegistrar.php';
// 内置杠杆文件由 Registry::init() 自动 glob 加载
// G3.7: Dynamic fitness tracker for lever scoring
require_once LINKED3_DIR . "src/Classes/MetaLever/MetaLeverFitnessTracker.php";
add_action('plugins_loaded', ['\\Linked3\\Classes\\MetaLever\\MetaLeverRegistry', 'init'], 5);
// v19.50.1: 统一注册所有模块的 system_prompt 钩子
add_action('plugins_loaded', ['\\Linked3\\Classes\\MetaLever\\MetaLeverHooksRegistrar', 'register'], 6);

// v19.51: 图示结构注册表 — 8 种结构 + 智能匹配
require_once LINKED3_DIR . 'src/Classes/Diagram/DiagramStructureRegistry.php';
add_action('plugins_loaded', ['\\Linked3\\Classes\\Diagram\\DiagramStructureRegistry', 'init'], 7);

// G3.2: Unified Content Pipeline
require_once LINKED3_DIR . "src/Classes/Content/Pipeline/ContentPipelineInterface.php";
require_once LINKED3_DIR . "src/Classes/Content/Pipeline/ContentPipelineRegistry.php";
add_action("init", ["\\Linked3\\Classes\\Content\\Pipeline\\ContentPipelineRegistry", "register_ajax"], 20);

// v19.53: 统一 AI 模型配置 — 消除 48 处硬编码模型名
require_once LINKED3_DIR . 'src/Classes/Core/ModelConfig.php';

// v20.0: 认知操作系统 (Cognitive Operating System) — 双公理 + 五部门 + 三代演化
// 变异-绞杀流程: COS 引擎作为新子系统嵌入, 逐步吸收 MetaLever 的决策路径
require_once LINKED3_DIR . 'src/Classes/CognitiveOS/COSEngine.php';
require_once LINKED3_DIR . 'src/Classes/CognitiveOS/Ajax/COSAjax.php';

// G7增量: 推荐引擎AJAX接口注册 (v16.0.27)
$g7_ajax_file = LINKED3_DIR . 'src/Classes/Genesis/GenesisRecommendationAjax.php';
if (file_exists($g7_ajax_file)) {
    require_once $g7_ajax_file;
}

// Top-level fatal capture so a broken module never produces a silent plugin.
// We surface every error via admin_notice so the site owner can diagnose.
$linked3_bootstrap_error = null;


// Wrap the class_exists + activation hook registration in try/catch — if
// autoload triggers a ParseError (e.g. a required file has a syntax error),
// class_exists itself throws and we must catch it here.
try {
    $linked3_core_available = class_exists('Linked3\\Includes\\Plugin');
} catch (\Throwable $e) {
    $linked3_bootstrap_error = sprintf(
        __('Linked3 自动加载错误:%s (位于 %s:%d)', 'linked3'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    $linked3_core_available = false;
}

if (!$linked3_core_available) {
    if (empty($linked3_bootstrap_error)) {
        $linked3_bootstrap_error = sprintf(
            /* translators: %s: class name. */
            __('Linked3 核心类 %s 无法加载。请检查 src/autoload.php 和 src/Includes/class-linked3-plugin.php 是否存在且可读。', 'linked3'),
            'Linked3\\Includes\\Plugin'
        );
    }
} else {
    // Activation hook — wrapped so a failure during activation does NOT
    // produce a silent fatal. We catch + record + surface via admin_notice.
    register_activation_hook(LINKED3_FILE, static function () use (&$linked3_bootstrap_error) {
        try {
            if (class_exists('Linked3\\Includes\\Activator')) {
                \Linked3\Includes\Activator::activate();
            }
        } catch (\Throwable $e) {
            $linked3_bootstrap_error = sprintf(
                'Linked3 activation fatal: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            if (function_exists('error_log')) {
                error_log('[linked3] activation fatal: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
            // Deactivate ourselves so the site isn't stuck.
            deactivate_plugins(plugin_basename(LINKED3_FILE));
            wp_die(
                '<div style="font-family:monospace;background:#fff;border:1px solid #ddd;padding:20px;margin:20px;">'
                . '<h2>Linked3 activation failed</h2>'
                . '<p><strong>Error:</strong> ' . esc_html($e->getMessage()) . '</p>'
                . '<p><strong>File:</strong> ' . esc_html($e->getFile()) . ':' . (int) $e->getLine() . '</p>'
                . '<p><strong>Trace:</strong></p><pre>' . esc_html($e->getTraceAsString()) . '</pre>'
                . '<p>Plugin has been deactivated. Please report this error.</p>'
                . '</div>',
                'Linked3 Activation Error',
                ['response' => 500, 'back_link' => true]
            );
        }
    });
    register_deactivation_hook(LINKED3_FILE, ['Linked3\\Includes\\Deactivator', 'deactivate']);
    register_uninstall_hook(LINKED3_FILE, ['Linked3\\Includes\\Uninstaller', 'uninstall']);

    add_action('plugins_loaded', static function () use (&$linked3_bootstrap_error) {
        // v6.0.0: 最终启动序列 (统一调用所有 Phase)
        // v27.9.2: 先确保 DependencyLoader 加载 (提供 linked3_container 等全局函数)
        try {
            // Step 1: 确保 DependencyLoader 已加载
            if (class_exists('\\Linked3\\Includes\\DependencyLoader')) {
                \Linked3\Includes\DependencyLoader::load();
            }
            // Step 2: 确保全局函数 linked3_container() 可用
            if (!function_exists('linked3_container') && class_exists('\\Linked3\\Includes\\Container')) {
                function linked3_container() { return \Linked3\Includes\Container::instance(); }
            }
            // Step 3: 启动 Bootstrap 序列
            if (class_exists('\\Linked3\\Classes\\E2E\\FinalBootstrap')) {
                \Linked3\Classes\E2E\FinalBootstrap::boot();
            } else {
                // 降级: 逐步启动
                if (class_exists('\\Linked3\\Classes\\E2E\\V54Bootstrap')) \Linked3\Classes\E2E\V54Bootstrap::boot();
                if (class_exists('\\Linked3\\Classes\\Agent\\AgentBootstrap')) \Linked3\Classes\Agent\AgentBootstrap::boot();
                if (class_exists('\\Linked3\\Classes\\AI\\Pipeline\\AIPipelineBootstrap')) \Linked3\Classes\AI\Pipeline\AIPipelineBootstrap::boot();
                if (class_exists('\\Linked3\\Classes\\Security\\SecurityBootstrap')) \Linked3\Classes\Security\SecurityBootstrap::boot();
                if (class_exists('\\Linked3\\Classes\\Billing\\BillingBootstrap')) \Linked3\Classes\Billing\BillingBootstrap::boot();
                if (class_exists('\\Linked3\\Classes\\Scale\\ScaleBootstrap')) \Linked3\Classes\Scale\ScaleBootstrap::boot();
            }
            // v6.5.0: 图示引擎核心
            // v27.9.0 (P0-A): 裸名 → FQCN, 修复 Bootstrap 永不执行
            if (class_exists('\\Linked3\\Classes\\Diagram\\DiagramBootstrap')) {
                \Linked3\Classes\Diagram\DiagramBootstrap::boot();
            }
            // v6.5.0: 图示生产级启动
            if (class_exists('\\Linked3\\Classes\\Diagram\\DiagramProductionBootstrap')) {
                \Linked3\Classes\Diagram\DiagramProductionBootstrap::boot();
            }
            // v6.6.0: Genesis 漫画脚本引擎
            if (class_exists('\\Linked3\\Classes\\Genesis\\GenesisBootstrap')) {
                \Linked3\Classes\Genesis\GenesisBootstrap::boot();
            }
        } catch (\Throwable $e) {
            $linked3_bootstrap_error = sprintf(
                __('Linked3 bootstrap 错误:%s (位于 %s:%d)', 'linked3'),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            if (function_exists('error_log')) {
                error_log('[linked3] v5.4.0 bootstrap error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }

        try {
            \Linked3\Includes\Plugin::instance()->run();
        } catch (\Throwable $e) {
            $linked3_bootstrap_error = sprintf(
                __('Linked3 启动失败:%s (位于 %s:%d)', 'linked3'),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            if (function_exists('error_log')) {
                error_log('[linked3] boot fatal: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
    }, 5);
}

// Always register the diagnostic notice — even if the plugin booted fine,
// this hook is cheap and gives us a reliable surface to surface any error
// captured above. Uses a closure (anonymous function) so `use` is legal.
add_action('admin_notices', static function () use (&$linked3_bootstrap_error) {
    if (empty($linked3_bootstrap_error) && empty($GLOBALS['linked3_early_errors'])) {
        return;
    }
    echo '<div class="notice notice-error"><p><strong>Linked3 AI:</strong> ';

    if (!empty($linked3_bootstrap_error)) {
        echo esc_html($linked3_bootstrap_error);
    }

    // Also surface any early errors captured by the ultra-early handler.
    if (!empty($GLOBALS['linked3_early_errors'])) {
        foreach ($GLOBALS['linked3_early_errors'] as $err) {
            echo '<br><code>' . esc_html($err['type']) . ': '
                . esc_html($err['message'])
                . ' in ' . esc_html($err['file']) . ':' . (int) $err['line']
                . '</code>';
        }
    }

    echo '</p></div>';
});
