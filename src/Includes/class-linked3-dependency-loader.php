<?php
/**
 * Layer 1/3: Dependency Loader.
 *
 * v4.3.8 refactor — moved from a 70-line hardcoded file list to a 3-phase
 * loader that combines an explicit ordered "skeleton" with recursive glob
 * discovery. New files dropped into `src/Classes/**` are now auto-loaded
 * without requiring registration here.
 *
 * Phase 1 — CORE_SKELETON (explicit, ordered):
 *   Traits, interfaces, base abstract classes and the 3-layer orchestrators
 *   themselves. These have strict dependency ordering (a trait must exist
 *   before a class that `use`s it is parsed) and total ~15 files.
 *
 * Phase 2 — GLOB_SCAN (recursive, unordered):
 *   Every `class-*.php` / `interface-*.php` / `trait-*.php` under
 *   `src/Classes/**` is require_once'd. The PSR-4 autoloader (registered
 *   in linked3.php before this method runs) resolves class-inheritance
 *   dependencies on demand, so glob order does not matter for class files.
 *
 * Phase 3 — MODULE_LOADERS (delegated):
 *   Each module ships its own sub-loader (implements static ::load()) for
 *   module-specific ordering needs (e.g. loading Action classes before the
 *   Registrar that references them). Hard-registered below + filterable
 *   via `linked3/dependency_loaders`.
 *
 * Error handling: every require_once is wrapped in try/catch so a single
 * broken file records the error and lets the rest of the plugin load.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

final class Linked3_Dependency_Loader
{
    /** @var string[] Per-file load errors (Populated when a require_once throws). */
    public static $load_errors = [];

    /** @var string[] Successfully loaded file paths (Populated during glob_scan). */
    private static $loaded_files = [];

    /**
     * Phase 1 — explicit ordered skeleton.
     *
     * Only files with strict load-order requirements belong here:
     *   - Security traits (used by every AJAX Action class)
     *   - Provider interface + base strategy (extended by every provider)
     *   - The 3-layer orchestrators themselves (Hook_Manager, Module_Init,
     *     this class)
     *   - Activator / Deactivator / Schema / Migration (boot-time critical)
     *   - Crypto + Safe_Remote + Logger (used during activation)
     *
     * @var string[] Paths relative to src/.
     */
    private const CORE_SKELETON = [
        // Security traits (v0.0.6) — MUST load before any class that uses them.
        'Includes/Traits/trait-check-admin-permissions.php',
        'Includes/Traits/trait-check-frontend-permissions.php',
        'Includes/Traits/trait-send-wp-error.php',
        'Includes/Traits/trait-check-plan-access.php',
        // HTTP + Crypto + Log (used during activation & by every provider).
        'Includes/Http/class-linked3-safe-remote.php',
        'Includes/class-linked3-crypto.php',
        'Includes/Log/class-linked3-logger.php',
        'Includes/Log/class-linked3-payload-sanitizer.php',
        // Lifecycle + i18n + DB (boot-time critical).
        'Includes/class-linked3-i18n.php',
        'Includes/class-linked3-activator.php',
        'Includes/class-linked3-deactivator.php',
        'Includes/class-linked3-uninstaller.php',
        'Includes/DB/class-linked3-schema.php',
        'Includes/DB/class-linked3-migration-runner.php',
        // Security watchdogs.
        'Includes/class-linked3-disallowed-nopriv-actions.php',
        'Classes/Security/class-linked3-ajax-auditor.php',
        'Classes/Security/class-linked3-rate-limiter.php',
        // Provider Strategy interface + base (extended by 4+ providers).
        'Classes/Core/Providers/interface-linked3-provider-strategy.php',
        'Classes/Core/Providers/class-linked3-base-provider-strategy.php',
        // 3-layer orchestrators themselves.
        'Includes/class-linked3-hook-manager.php',
        'Includes/class-linked3-module-initializer.php',
        // v4.4.2/v4.5.3: DI container. NotFoundException MUST load before
        // the Container (the container throws it). No PSR-11 interface
        // dependency — the container is self-contained.
        'Includes/class-linked3-not-found-exception.php',
        'Includes/class-linked3-container.php',
        // v10.7.7: Event bus bridge functions (linked3_dispatch / linked3_subscribe).
        // Restores functions that were defined in the dead Classes/Core/EventBus/
        // file (never loaded). MUST load after the container so linked3_container()
        // is available. See src/Includes/functions-events.php for details.
        'Includes/functions-events.php',
        // v4.4.3: Base_Repository + Query_Builder.
        'Includes/DB/class-linked3-query-builder.php',
        'Includes/DB/class-linked3-base-repository.php',
        // v4.8.0: Shared template seed trait (used by both Template managers).
        'Classes/Templates/trait-linked3-template-seed.php',
        // v4.9.4: Billing event repository (used by REST webhook + Business_Optimizer).
        'Classes/Billing/class-linked3-billing-event-repository.php',
        // v25.0: New architecture components.
        'Includes/class-linked3-option-repository.php',
        'Includes/class-linked3-secret-vault.php',
        'Includes/class-linked3-request.php',
        'Includes/class-linked3-ajax-guard.php',
        'Includes/class-linked3-config-registry.php',
        'Includes/class-linked3-service-locator.php',
        'Includes/class-linked3-performance-monitor.php',
        'Classes/AI/Pipeline/class-linked3-provider-registry.php',
    ];

    /**
     * Phase 3 — hard-registered module sub-loaders.
     *
     * Each implements `public static function load()` and is responsible for
     * its own module's sub-tree (e.g. ContentWriter loads its Prompt builders,
     * Input sources, Ajax Actions, then the Registrar).
     *
     * @var string[] Fully-qualified class names.
     */
    private const MODULE_LOADERS = [
        'Linked3\\Classes\\ContentWriter\\ContentWriterDependenciesLoader',
        'Linked3\\Classes\\SEO\\Linked3_SEO_Dependencies_Loader',
        'Linked3\\Classes\\Publish\\Linked3_Publish_Collect_Dependencies_Loader',
        'Linked3\\Classes\\Chat\\ChatDependenciesLoader',
        'Linked3\\Classes\\AutoGPT\\Linked3_AutoGPT_Dependencies_Loader',
        'Linked3\\Classes\\WooCommerce\\WcFormsSpeechDependenciesLoader',
        'Linked3\\Classes\\Dashboard\\Linked3_Dashboard_Dependencies_Loader',
        'Linked3\\Classes\\Distribute\\DistributeDependenciesLoader',
        // v5.1.0: Pipeline Orchestrator (the production factory conductor).
        'Linked3\\Classes\\Pipeline\\PipelineDependenciesLoader',
        // v5.2.0: V15 视觉提示词系统.
        'Linked3\\Classes\\V15\\V15DependenciesLoader',
        
        // v27.0.0: OS module — renamed from V18 实验室. Loads new English-named
        // classes AND the backward-compatibility alias layer that maps old
        // V18 pinyin class names (Hong_Liu, Ru_Liu, etc.) to new OS names.
        'Linked3\\Classes\\OS\\Linked3_OS_Dependencies_Loader',
    ];

    /**
     * Load all required files in three phases (skeleton → glob → modules).
     *
     * Modules can register their own sub-loader via the
     * `linked3/dependency_loaders` filter returning a fully-qualified class
     * name implementing a static ::load() method.
     *
     * @return void
     */
    public static function load() : void
    {
        self::load_skeleton();
        self::glob_scan_classes();
        self::load_module_loaders();
    }

    /**
     * Phase 1 — load the explicit ordered skeleton.
     *
     * @return void
     */
    private static function load_skeleton()
    {
        foreach (self::CORE_SKELETON as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            self::require_safe($path, $relative);
        }
    }

    /**
     * Phase 2 — recursively glob `src/Classes/**` for every PHP class /
     * interface / trait file and require_once it.
     *
     * The PSR-4 autoloader (registered in linked3.php) resolves class
     * inheritance on demand, so the order in which these files are loaded
     * does not matter — PHP will pull in the parent class via the autoloader
     * the moment a child class is parsed.
     *
     * We skip the module Dependencies_Loaders themselves (Phase 3 owns those)
     * to avoid double-loading, but require_once makes that harmless anyway.
     *
     * @return void
     */
    private static function glob_scan_classes()
    {
        $root = LINKED3_DIR . 'src/Classes';
        if (!is_dir($root)) {
            return;
        }

        // Recursive iterator over every .php file under src/Classes/.
        // We sort by path so the load order is deterministic across OSes
        // (PHP's glob/iterator order is filesystem-dependent otherwise).
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $files = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $name = $file->getFilename();
            // Only load class- / interface- / trait- prefixed files. This
            // skips standalone scripts (e.g. helper .php files that define
            // functions) which may have their own load-order requirements
            // and are better owned by the module sub-loaders.
            if (!preg_match('/^(class|interface|trait)-.+\.php$/', $name)) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files);

        // ── FIX v16.0.1: Memory safety guard ──────────────────────────────
        // WordPress Playground and shared hosts may have tight memory limits.
        // If we're approaching the limit, stop loading — the autoloader will
        // lazy-load any remaining classes on demand.
        $memory_limit = ini_get('memory_limit');
        $limit_bytes = $memory_limit && $memory_limit !== '-1'
            ? self::parse_memory_string($memory_limit)
            : 0;

        foreach ($files as $path) {
            // If we've used > 85% of the memory limit, stop eager-loading.
            // The PSR-4 autoloader will handle the rest on demand.
            if ($limit_bytes > 0) {
                $used = memory_get_usage(true);
                if ($used > $limit_bytes * 0.85) {
                    self::$load_errors[] = sprintf(
                        'glob_scan: stopped at %d/%d files (memory at %dMB / %dMB limit)',
                        count(self::$loaded_files ?? []),
                        count($files),
                        round($used / 1048576),
                        round($limit_bytes / 1048576)
                    );
                    break;
                }
            }

            // Compute a display-friendly relative path for error reporting.
            $relative = ltrim(str_replace(LINKED3_DIR . 'src/', '', $path), '/');
            self::require_safe($path, $relative);
        }
    }

    /**
     * Convert a PHP memory shorthand string (e.g. "256M", "1G") to bytes.
     *
     * @param string $val
     * @return int
     */
    private static function parse_memory_string($val)
    {
        $val = trim($val);
        $last = strtolower(substr($val, -1));
        $num = (int) $val;
        switch ($last) {
            case 'g': $num *= 1024;
            case 'm': $num *= 1024;
            case 'k': $num *= 1024;
        }
        return $num;
    }

    /**
     * Phase 3 — invoke each module sub-loader's ::load().
     *
     * Merges the hard-registered loaders with any added via the
     * `linked3/dependency_loaders` filter, then calls each one inside
     * a try/catch.
     *
     * @return void
     */
    private static function load_module_loaders()
    {
        $module_loaders = array_merge(
            self::MODULE_LOADERS,
            (array) apply_filters('linked3/dependency_loaders', [])
        );

        foreach ($module_loaders as $loader_class) {
            try {
                if (class_exists($loader_class) && method_exists($loader_class, 'load')) {
                    call_user_func([$loader_class, 'load']);
                }
            } catch (\Throwable $e) {
                self::$load_errors[] = sprintf(
                    'module %s: %s (in %s:%d)',
                    $loader_class,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                );
                if (function_exists('error_log')) {
                    error_log('[linked3] module load fail: ' . $loader_class . ' → ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * require_once a file inside a try/catch so a single broken file cannot
     * kill the whole plugin. Errors are recorded to self::$load_errors for
     * display via the admin notice in Hook_Manager.
     *
     * @param string $path     Absolute filesystem path.
     * @param string $relative Display path for error messages.
     * @return void
     */
    private static function require_safe($path, $relative)
    {
        if (!file_exists($path)) {
            // Silently skip missing skeleton entries — they may belong to
            // modules that are not yet bundled (e.g. Pro-only files).
            return;
        }
        try {
            require_once $path;
            self::$loaded_files[] = $relative;
        } catch (\Throwable $e) {
            self::$load_errors[] = sprintf(
                '%s: %s (in %s:%d)',
                $relative,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            if (function_exists('error_log')) {
                error_log('[linked3] load fail: ' . $relative . ' → ' . $e->getMessage());
            }
        }
    }
}
