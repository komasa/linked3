<?php
/**
 * PSR-4 autoloader for Linked3.
 *
 * Namespace mirror:
 *   Linked3\Includes\Foo       -> src/Includes/Foo.php  (or Foo.php)
 *   Linked3\Includes\Foo\Bar   -> src/Includes/Foo/class-bar.php
 *   Linked3\Classes\ContentWriter\Writer -> src/Classes/ContentWriter/class-writer.php
 *
 * @package Linked3
 *
 * INTENTIONAL GLOBAL NAMESPACE (P11 exception):
 *   This file registers the spl_autoload_register() callback that maps
 *   Linked3\* namespace prefixes to filesystem paths. It MUST live in the
 *   global namespace — adding a `namespace` declaration here would break
 *   the autoloader because the callback closure must be callable from
 *   PHP's global autoload stack. This is the single sanctioned exception
 *   to the "every file has a namespace" rule; all other source files
 *   under src/ carry an explicit namespace declaration.
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function ($class) {
    $prefix = 'Linked3\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    if (empty($parts)) {
        return;
    }

    // Last segment is the class/trait/interface name. WordPress naming:
    //   class-{lower-hyphenated}.php   for classes
    //   trait-{lower-hyphenated}.php   for traits
    //   interface-{lower-hyphenated}.php for interfaces
    // Our symbols use Linked3_X_Y_Z style (underscore); files use hyphens.
    $last = array_pop($parts);
    $lastLower = strtolower(str_replace('_', '-', $last));

    // Try all three prefixes (class- / trait- / interface-) since the
    // autoloader cannot know from the symbol name alone whether it is a
    // class, trait, or interface.
    //
    // Naming convention in this codebase is inconsistent: some interfaces
    // are named Linked3_X_Interface (file: X.php, no
    // "-interface" suffix on file), others are Linked3_X (file:
    // X.php). To cover both, when the symbol ends with
    // "-interface" we ALSO try stripping that suffix from the filename.
    $candidates = [];
    if (strpos($lastLower, 'trait-') === 0) {
        $candidates[] = 'trait-' . substr($lastLower, 6) . '.php';
        $candidates[] = 'class-' . $lastLower . '.php';
    } elseif (strpos($lastLower, 'interface-') === 0) {
        $candidates[] = 'interface-' . substr($lastLower, 10) . '.php';
        $candidates[] = 'class-' . $lastLower . '.php';
    } else {
        $candidates[] = 'class-' . $lastLower . '.php';
        $candidates[] = 'trait-' . $lastLower . '.php';
        $candidates[] = 'interface-' . $lastLower . '.php';
        // If the symbol ends with "-interface" / "-trait", also try the
        // file without that suffix (matches our file-naming convention).
        if (substr($lastLower, -10) === '-interface') {
            $candidates[] = 'interface-' . substr($lastLower, 0, -10) . '.php';
        }
        if (substr($lastLower, -6) === '-trait') {
            $candidates[] = 'trait-' . substr($lastLower, 0, -6) . '.php';
        }
    }

    $dir = LINKED3_DIR . 'src/' . implode('/', $parts) . '/';
    foreach ($candidates as $file) {
        $path = $dir . $file;
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }

    // Fallback: ClassName.php (PascalCase direct).
    $fallback = $dir . $last . '.php';
    if (file_exists($fallback)) {
        require_once $fallback;
    }
});

/**
 * v9.1.0 性能说明:
 *   - PHP 的 require_once 内部已有类加载缓存, 同类第二次加载不会进 autoload 回调
 *   - 现有 autoload 已用 spl_autoload_register (按需加载, 非 require_all)
 *   - composer.json 已配 classmap, 若跑 `composer dump-autoload` 会生成优化映射
 *   - 不做 PSR-4 切换 (类名混合 Linked3_X_Y / Linked3\NS\X 两种风格, 切换风险大)
 */

// ─── Legacy Alias Registry ──────────────────────────────────────────────────
// Load the central alias registry for backward compatibility with old
// Linked3_* class names. Uses lazy resolution via spl_autoload_register
// to avoid class_exists false-positives.
$compat_path = LINKED3_DIR . 'src/Includes/Compat/LegacyAliasRegistry.php';
if (file_exists($compat_path)) {
    require_once $compat_path;
}
