<?php
/**
 * Plugin Name:       Linked3 AI
 * Plugin URI:        https://linked3.com
 * Description:       Commercial self-evolution AI engine for WordPress — multi-model AI, SEO, content automation, SaaS billing. v18.5 adds Book Factory (YAML-driven 6-step automated book writing). Successor to Linkreate AI v2.9.6. v20.4 fixes COS: real AI generation in EX department, real Skill content, real lever chain analysis. v27.1.0: V18→OS 重构 + Genesis/Diagram/MetaLever 模块 namespace 补全（90 文件）+ 54 个 AJAX 委托方法修复 + 超长方法拆分。
 * Version:           27.7.1
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
define('LINKED3_VERSION', '27.7.1');
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
// -----------------------------------------------------------------------------
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>'
            . esc_html(sprintf(
                /* translators: %s: PHP version. */
                __('Linked3 AI 需要 PHP 7.4 或更高版本。当前运行 %s,请升级 PHP。', 'linked3'),
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

// 隐藏左侧子菜单中的"编辑/新增"等 CPT 默认子项, 但保留 Seed DNA 等业务子菜单
// v9.1.2 修复: 旧代码 display:none 全部子菜单导致 Seed DNA 菜单不可见
add_action('admin_head', static function () {
    echo '<style>
    /* 只隐藏 CPT 自动生成的 edit.php / post-new.php 子菜单, 保留业务子菜单 */
    #toplevel_page_linked3-dashboard .wp-submenu li a[href*="edit.php?post_type=linked3_seed"] { display:none; }
    #toplevel_page_linked3-dashboard .wp-submenu li a[href*="post-new.php?post_type=linked3_seed"] { display:none; }
    </style>';
    // 加载 AIpower 风格 CSS
    $css_file = LINKED3_DIR . 'assets/css/linked3-admin.css';
    if (file_exists($css_file)) {
        echo '<style>' . file_get_contents($css_file) . '</style>';
    }
    // v16.0.18 [公理α/β]: 万兴2风格设计 token — 单一配置源替代N处inline style
    $ws2_css = LINKED3_DIR . 'assets/css/linked3-wansheng2.css';
    if (file_exists($ws2_css)) {
        echo '<style>' . file_get_contents($ws2_css) . '</style>';
    }
    // v16.0.17 [公理α/β]: 生态面板CSS Grid自适应布局 — 0维Grid替代N维media query
    $eco_layout_css = LINKED3_DIR . 'assets/css/linked3-eco-layout.css';
    if (file_exists($eco_layout_css)) {
        echo '<style>' . file_get_contents($eco_layout_css) . '</style>';
    }
    // v12.0: Global UI Design System — Linear/Vercel aesthetic
    $ui_css = LINKED3_DIR . 'assets/css/linked3-admin-ui.css';
    if (file_exists($ui_css)) {
        echo '<style>' . file_get_contents($ui_css) . '</style>';
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
        try {
            if (class_exists('FinalBootstrap')) {
                FinalBootstrap::boot();
            } else {
                // 降级: 逐步启动
                if (class_exists('V54Bootstrap')) V54Bootstrap::boot();
                if (class_exists('AgentBootstrap')) AgentBootstrap::boot();
                if (class_exists('AIPipelineBootstrap')) AIPipelineBootstrap::boot();
                if (class_exists('SecurityBootstrap')) SecurityBootstrap::boot();
                if (class_exists('BillingBootstrap')) BillingBootstrap::boot();
                if (class_exists('ScaleBootstrap')) ScaleBootstrap::boot();
            }
            // v6.5.0: 图示引擎核心
            if (class_exists('DiagramBootstrap')) {
                DiagramBootstrap::boot();
            }
            // v6.5.0: 图示生产级启动
            if (class_exists('DiagramProductionBootstrap')) {
                DiagramProductionBootstrap::boot();
            }
            // v6.6.0: Genesis 漫画脚本引擎
            if (class_exists('GenesisBootstrap')) {
                GenesisBootstrap::boot();
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
