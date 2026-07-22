<?php
/**
 * WP Early Error Handler — Standalone Reusable Module
 * ====================================================
 *
 * A drop-in early error handler for ANY WordPress plugin. 
 *
 * THE PROBLEM IT SOLVES
 * ---------------------
 * PHP fatal errors (E_COMPILE_ERROR, E_ERROR) HALT execution — you can only
 * see ONE error at a time. Fix it, reload, see the NEXT error. This is
 * "whack-a-mole" debugging. WordPress's default "critical error" page shows
 * nothing useful at all.
 *
 * THIS MODULE'S THREE-LAYER DEFENSE
 * ---------------------------------
 * Layer 1: BATCH SYNTAX SCAN (runtime)
 *   Scans every .php file using token_get_all(TOKEN_PARSE) BEFORE any file
 *   is loaded. Shows ALL syntax errors at once. Catches missing semicolons,
 *   unbalanced braces, use-before-namespace, etc.
 *
 * Layer 2: REQUIRE PATH CHECK (runtime)  
 *   Validates every require_once/include path in the main plugin file.
 *   Catches the #1 activation fatal: PSR-4 file rename but require path
 *   not updated.
 *
 * Layer 3: INTERFACE COMPATIBILITY SCAN (runtime, NEW in v2.0)
 *   Scans all interface implementations for return type mismatches and
 *   missing parameter type hints. Catches errors like:
 *     Interface says `: array` → impl says `: mixed` → E_COMPILE_ERROR
 *   This is the class of error that v27.6.5's handler COULDN'T catch in
 *   batch — because `: mixed` is syntactically valid PHP, just incompatible.
 *   NOW ALL such errors are shown at once, not one-at-a-time.
 *
 * Layer 4: RUNTIME SHUTDOWN HANDLER (runtime)
 *   Catches E_ERROR, E_PARSE, E_COMPILE_ERROR etc. with stack traces.
 *   Shows the REAL error instead of WordPress's generic "critical error" page.
 *
 * Layer 5: ERROR PERSISTENCE
 *   All errors written to error_log → debug.log AND stored in a WP option
 *   that survives plugin deactivation. A standalone diagnostic endpoint
 *   can read errors even when the plugin is NOT active.
 *
 * INSTALLATION (3 steps)
 * ----------------------
 *   1. Copy this file into your plugin:
 *        /wp-content/plugins/your-plugin/lib/wp-early-error-handler.php
 *
 *   2. At the VERY TOP of your main plugin file (right after ABSPATH guard
 *      and plugin header comment), add:
 *
 *        require_once __DIR__ . '/lib/wp-early-error-handler.php';
 *        wp_early_error_handler_init([
 *            'plugin_name' => 'Your Plugin Name',
 *            'plugin_dir'  => __DIR__,
 *            'main_file'   => __FILE__,
 *        ]);
 *
 *   3. Done. Every page load now scans all files and shows ALL errors at once.
 *
 * CONFIGURATION OPTIONS
 * ---------------------
 *   'plugin_name'           => string  Display name on error pages.
 *   'plugin_dir'            => string  Root directory to scan.
 *   'main_file'             => string  Main plugin file (for require path check).
 *   'scan_on_load'          => bool    Batch-scan on every load. Default: true.
 *   'skip_dirs'             => array   Subdirs to skip. Default: node_modules,.git,vendor,tests/bin
 *   'check_require_paths'   => bool    Check require_once targets exist. Default: true.
 *   'check_interfaces'      => bool    Scan interface impl compatibility. Default: true.
 *   'disable_wp_handler'    => bool    Disable WP's fatal handler. Default: true.
 *   'force_display_errors'  => bool    Force display_errors=1. Default: true.
 *   'persist_errors'        => bool    Store errors in option + error_log. Default: true.
 *   'option_name'           => string  WP option key for error persistence.
 *
 * @version   2.0.0
 * @license   MIT
 * @author    Breakthrough Innovator (Linked3 AI project)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Guard against double-load.
if (defined('WP_EEH_LOADED')) {
    return;
}
define('WP_EEH_LOADED', true);
define('WP_EEH_VERSION', '2.1.0-standalone');

/**
 * Initialize the early error handler.
 */
if (!function_exists('wp_early_error_handler_init')) {
    function wp_early_error_handler_init($opts = [])
    {
        $opts = array_merge([
            'plugin_name'          => 'WordPress Plugin',
            'plugin_dir'           => dirname(__DIR__),
            'main_file'            => '',
            'scan_on_load'         => true,
            'skip_dirs'            => ['node_modules', '.git', 'vendor', 'tests/bin'],
            'disable_wp_handler'   => true,
            'force_display_errors' => true,
            'check_require_paths'  => true,
            'check_interfaces'     => true,
            'persist_errors'       => true,
            'option_name'          => 'wp_eeh_last_errors',
        ], $opts);

        $GLOBALS['wp_eeh_config'] = $opts;

        if (!defined('WP_EEH_PLUGIN_NAME')) {
            define('WP_EEH_PLUGIN_NAME', $opts['plugin_name']);
        }
        if (!defined('WP_EEH_PLUGIN_DIR')) {
            define('WP_EEH_PLUGIN_DIR', $opts['plugin_dir']);
        }

        // 1. Force PHP to surface every error.
        if ($opts['force_display_errors'] && (!defined('WP_DEBUG') || !WP_DEBUG)) {
            @ini_set('display_errors', '1');
            @ini_set('display_startup_errors', '1');
            if (defined('WP_CONTENT_DIR')) {
                @ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
            }
            error_reporting(E_ALL);
        }

        // 2. Disable WordPress's fatal error handler.
        if ($opts['disable_wp_handler']) {
            if (!defined('WP_FATAL_ERROR_HANDLER_ENABLED')) {
                define('WP_FATAL_ERROR_HANDLER_ENABLED', false);
            }
            if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) {
                define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
            }
            if (function_exists('add_filter')) {
                add_filter('wp_fatal_error_handler_enabled', '__return_false', 1);
            }
        }

        // 3. Batch syntax scan.
        if ($opts['scan_on_load']) {
            $errors = wp_eeh_batch_scan($opts['plugin_dir'], $opts['skip_dirs']);

            // 3b. Require path validation.
            if ($opts['check_require_paths'] && !empty($opts['main_file'])) {
                $path_errors = wp_eeh_check_require_paths($opts['main_file'], $opts['plugin_dir']);
                $errors = array_merge($errors, $path_errors);
            }

            // 3c. Interface compatibility scan (NEW in v2.0).
            if ($opts['check_interfaces']) {
                $iface_errors = wp_eeh_scan_interface_compatibility($opts['plugin_dir'], $opts['skip_dirs']);
                $errors = array_merge($errors, $iface_errors);
            }

            if (!empty($errors)) {
                $GLOBALS['wp_eeh_errors'] = $errors;
                wp_eeh_persist_errors($errors, 'batch_scan', $opts);
                wp_eeh_render_batch_errors($errors, $opts['plugin_name']);
                exit;
            }
        }

        // 4. Register shutdown handler for runtime fatals.
        register_shutdown_function('wp_eeh_shutdown_handler');

        // 4b. Admin notices for persisted errors.
        if ($opts['persist_errors'] && function_exists('add_action')) {
            add_action('admin_notices', 'wp_eeh_admin_notice_persisted_errors');
        }
    }
}

// =============================================================================
// Layer 1: Batch syntax scanner.
// =============================================================================
if (!function_exists('wp_eeh_batch_scan')) {
    function wp_eeh_batch_scan($plugin_dir, $skip_dirs = [])
    {
        $errors = [];
        if (!is_dir($plugin_dir)) return $errors;

        $skip_paths = [];
        foreach ($skip_dirs as $d) {
            $skip_paths[] = $plugin_dir . '/' . $d;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($plugin_dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
        } catch (Throwable $e) {
            return $errors;
        }

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;

            $path = $file->getPathname();

            $skip = false;
            foreach ($skip_paths as $skip_path) {
                if (strpos($path, $skip_path) === 0) { $skip = true; break; }
            }
            if ($skip) continue;

            $source = @file_get_contents($path);
            if ($source === false) continue;

            $rel = wp_eeh_relpath($path, $plugin_dir);

            // token_get_all with TOKEN_PARSE catches syntax errors.
            try {
                token_get_all($source, TOKEN_PARSE);
            } catch (ParseError $e) {
                $errors[] = [
                    'type'    => 'ParseError',
                    'message' => $e->getMessage(),
                    'file'    => $rel,
                    'line'    => $e->getLine(),
                ];
                continue;
            } catch (Error $e) {
                $errors[] = [
                    'type'    => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $rel,
                    'line'    => $e->getLine(),
                ];
                continue;
            } catch (Throwable $e) {
                $errors[] = [
                    'type'    => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $rel,
                    'line'    => $e->getLine(),
                ];
                continue;
            }

            // Check for use-before-namespace.
            $ns_line = wp_eeh_find_namespace_line($source);
            if ($ns_line !== false) {
                $bad_line = wp_eeh_find_statement_before_namespace($source, $ns_line);
                if ($bad_line !== false) {
                    $lines = explode("\n", $source);
                    $bad_content = trim($lines[$bad_line]);
                    $is_use = (strpos($bad_content, 'use ') === 0);
                    $errors[] = [
                        'type'    => $is_use ? 'UseBeforeNamespace' : 'StatementBeforeNamespace',
                        'message' => $is_use
                            ? 'use statement appears before namespace declaration. use must come AFTER namespace.'
                            : 'Statement before namespace declaration: "' . substr($bad_content, 0, 100) . '"',
                        'file'    => $rel,
                        'line'    => $bad_line + 1,
                    ];
                }
            }
        }

        return $errors;
    }
}

// =============================================================================
// Layer 3 (NEW): Interface compatibility scanner.
// Scans ALL interface implementations for return type mismatches and
// missing parameter type hints. Finds ALL such errors at once — runtime
// PHP can only show ONE at a time (fatal halts execution).
// =============================================================================
if (!function_exists('wp_eeh_scan_interface_compatibility')) {
    function wp_eeh_scan_interface_compatibility($plugin_dir, $skip_dirs = [])
    {
        $errors = [];
        if (!is_dir($plugin_dir)) return $errors;

        // Phase 1: Collect all PHP files.
        $php_files = wp_eeh_collect_php_files($plugin_dir, $skip_dirs);

        // Phase 2: Parse interfaces and classes.
        $interfaces = [];  // FQCN → [file, methods]
        $classes = [];     // FQCN → [file, implements, extends, methods, use_map]

        foreach ($php_files as $path) {
            $parsed = wp_eeh_parse_php_symbols($path);
            if (!$parsed) continue;

            $ns = $parsed['namespace'];
            $use_map = $parsed['use_map'];
            $fqn = function ($name) use ($ns) { return $ns ? $ns . '\\' . $name : $name; };

            foreach ($parsed['interfaces'] as $name => $data) {
                $interfaces[$fqn($name)] = ['file' => $path, 'methods' => $data['methods']];
            }
            foreach ($parsed['classes'] as $name => $data) {
                $classes[$fqn($name)] = [
                    'file' => $path,
                    'implements' => $data['implements'],
                    'extends' => $data['extends'],
                    'methods' => $data['methods'],
                    'use_map' => $use_map,
                ];
            }
        }

        // Phase 3: Check each class against its interfaces.
        foreach ($classes as $class_fqn => $class_data) {
            if (empty($class_data['implements'])) continue;

            // Collect all interfaces (including inherited via parent chain).
            $all_ifaces = wp_eeh_collect_inherited_interfaces($class_fqn, $classes, $interfaces);

            foreach ($all_ifaces as $iface_fqn) {
                $iface = $interfaces[$iface_fqn] ?? null;
                if (!$iface) continue;

                foreach ($iface['methods'] as $method_name => $iface_method) {
                    $impl_method = $class_data['methods'][$method_name] ?? null;
                    if (!$impl_method) continue;

                    $file_errors = wp_eeh_compare_method_sigs(
                        $iface_method,
                        $impl_method,
                        $iface_fqn,
                        $class_fqn,
                        $method_name
                    );

                    foreach ($file_errors as $e) {
                        $errors[] = array_merge($e, ['file' => wp_eeh_relpath($class_data['file'], $plugin_dir)]);
                    }
                }
            }
        }

        return $errors;
    }
}

if (!function_exists('wp_eeh_collect_php_files')) {
    function wp_eeh_collect_php_files($dir, $skip_dirs = [])
    {
        $files = [];
        $skip_paths = [];
        foreach ($skip_dirs as $d) {
            $skip_paths[] = $dir . '/' . $d;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_FIRST
            );
        } catch (Throwable $e) {
            return $files;
        }

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $path = $file->getPathname();
            $skip = false;
            foreach ($skip_paths as $sp) {
                if (strpos($path, $sp) === 0) { $skip = true; break; }
            }
            if (!$skip) $files[] = $path;
        }

        return $files;
    }
}

if (!function_exists('wp_eeh_parse_php_symbols')) {
    function wp_eeh_parse_php_symbols($file_path)
    {
        $src = @file_get_contents($file_path);
        if ($src === false) return null;

        // Extract namespace.
        $ns_match = [];
        preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $src, $ns_match);
        $namespace = $ns_match[1] ?? '';

        // Extract use statements (alias resolution).
        $use_map = [];
        if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $src, $use_matches, PREG_SET_ORDER)) {
            foreach ($use_matches as $um) {
                $fqn = $um[1];
                $alias = $um[2] ?? (($bs = strrpos($fqn, '\\')) !== false ? substr($fqn, $bs + 1) : $fqn);
                $use_map[$alias] = $fqn;
            }
        }

        // Remove comments.
        $clean = preg_replace('!/\*[\s\S]*?\*/!', '', $src);
        $clean = preg_replace('!//[^\n]*!', '', $clean);
        $clean = preg_replace('!#[^\n]*!', '', $clean);

        $interfaces = [];
        $classes = [];

        // Match interface declarations.
        if (preg_match_all('/interface\s+(\w+)\s*(?:extends\s+([\w\\\\,\s]+))?\s*\{/', $clean, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $idx => $name_match) {
                $name = $name_match[0];
                $body_start = $m[0][$idx][1] + strlen($m[0][$idx][0]);
                $methods = wp_eeh_extract_methods($clean, $body_start);
                $interfaces[$name] = ['methods' => $methods, 'extends' => trim($m[2][$idx][0] ?? '')];
            }
        }

        // Match class declarations.
        if (preg_match_all('/(?:abstract\s+|final\s+)?class\s+(\w+)\s*(?:extends\s+([\w\\\\]+))?\s*(?:implements\s+([\w\\\\,\s]+))?\s*\{/', $clean, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $idx => $name_match) {
                $name = $name_match[0];
                $extends = trim($m[2][$idx][0] ?? '');
                $implements_str = trim($m[3][$idx][0] ?? '');
                $implements = $implements_str ? array_map('trim', explode(',', $implements_str)) : [];
                $body_start = $m[0][$idx][1] + strlen($m[0][$idx][0]);
                $methods = wp_eeh_extract_methods($clean, $body_start);
                $classes[$name] = ['extends' => $extends, 'implements' => $implements, 'methods' => $methods];
            }
        }

        return ['namespace' => $namespace, 'use_map' => $use_map, 'interfaces' => $interfaces, 'classes' => $classes];
    }
}

if (!function_exists('wp_eeh_extract_methods')) {
    function wp_eeh_extract_methods($src, $start)
    {
        $methods = [];
        $len = strlen($src);
        $i = $start;
        $depth = 1;

        while ($i < $len && $depth > 0) {
            if ($src[$i] === '{') $depth++;
            elseif ($src[$i] === '}') $depth--;

            if ($depth === 1 && substr($src, $i, 8) === 'function') {
                $sig_start = $i;
                $j = $i + 8;
                while ($j < $len && ctype_space($src[$j])) $j++;

                $name = '';
                while ($j < $len && (ctype_alnum($src[$j]) || $src[$j] === '_')) {
                    $name .= $src[$j];
                    $j++;
                }

                if ($name) {
                    // Find end of signature ({ or ;).
                    $sig_end = $j;
                    $paren_depth = 0;
                    while ($sig_end < $len) {
                        if ($src[$sig_end] === '(') $paren_depth++;
                        elseif ($src[$sig_end] === ')') $paren_depth--;
                        elseif ($src[$sig_end] === '{' && $paren_depth === 0) break;
                        elseif ($src[$sig_end] === ';' && $paren_depth === 0) break;
                        $sig_end++;
                    }

                    $sig = substr($src, $sig_start, $sig_end - $sig_start);

                    // Return type.
                    $ret_match = [];
                    preg_match('/\)\s*:\s*(\??[\w\\\\|]+)/', $sig, $ret_match);
                    $return_type = $ret_match[1] ?? '';

                    // Parameters.
                    $param_match = [];
                    preg_match('/\(([^)]*)\)/', $sig, $param_match);
                    $param_str = trim($param_match[1] ?? '');
                    $params = [];
                    if ($param_str) {
                        foreach (explode(',', $param_str) as $p) {
                            $p = trim($p);
                            $pm = [];
                            if (preg_match('/^(?:(\??[\w\\\\|]+)\s+)?(?:&\s*)?\$(\w+)/', $p, $pm)) {
                                $params[] = ['type' => $pm[1] ?? '', 'name' => $pm[2]];
                            }
                        }
                    }

                    $methods[$name] = ['returnType' => $return_type, 'params' => $params];
                }
            }
            $i++;
        }

        return $methods;
    }
}

if (!function_exists('wp_eeh_collect_inherited_interfaces')) {
    function wp_eeh_collect_inherited_interfaces($class_fqn, $classes, $interfaces)
    {
        $result = new \ArrayObject();  // use as set
        $visited = [$class_fqn => true];
        $current_fqn = $class_fqn;
        $current = $classes[$class_fqn] ?? null;

        while ($current) {
            $use_map = $current['use_map'] ?? [];
            foreach ($current['implements'] as $iface_name) {
                $iface_fqn = wp_eeh_resolve_fqn($iface_name, $current_fqn, $interfaces, $use_map);
                if ($iface_fqn && isset($interfaces[$iface_fqn])) {
                    $result[$iface_fqn] = true;
                }
            }

            if (!$current['extends']) break;

            $parent_fqn = wp_eeh_resolve_fqn($current['extends'], $current_fqn, $classes, $use_map);
            if (!$parent_fqn || isset($visited[$parent_fqn])) break;
            $visited[$parent_fqn] = true;

            $current_fqn = $parent_fqn;
            $current = $classes[$parent_fqn] ?? null;
        }

        return array_keys($result->getArrayCopy());
    }
}

if (!function_exists('wp_eeh_resolve_fqn')) {
    function wp_eeh_resolve_fqn($name, $context_fqn, $symbol_table, $use_map = [])
    {
        // Fully qualified (starts with \).
        if ($name[0] ?? '' === '\\') {
            $name = ltrim($name, '\\');
            if (isset($symbol_table[$name])) return $name;
            return null;
        }
        // Use import alias.
        if (!empty($use_map) && isset($use_map[$name])) {
            $fqn = $use_map[$name];
            if (isset($symbol_table[$fqn])) return $fqn;
        }
        // Direct lookup.
        if (isset($symbol_table[$name])) return $name;
        // Try with context namespace.
        $ns = '';
        $last_bs = strrpos($context_fqn, '\\');
        if ($last_bs !== false) {
            $ns = substr($context_fqn, 0, $last_bs);
        }
        if ($ns) {
            $candidate = $ns . '\\' . $name;
            if (isset($symbol_table[$candidate])) return $candidate;
        }
        return null;
    }
}

if (!function_exists('wp_eeh_compare_method_sigs')) {
    function wp_eeh_compare_method_sigs($iface_method, $impl_method, $iface_fqn, $class_fqn, $method_name)
    {
        $errors = [];
        $iface_name = substr(strrchr($iface_fqn, '\\'), 1) ?: $iface_fqn;
        $class_name = substr(strrchr($class_fqn, '\\'), 1) ?: $class_fqn;

        // Return type check.
        $iface_ret = $iface_method['returnType'];
        $impl_ret = $impl_method['returnType'];

        if ($iface_ret && $impl_ret) {
            if ($iface_ret !== $impl_ret && !wp_eeh_is_covariant($iface_ret, $impl_ret)) {
                $errors[] = [
                    'type'    => 'InterfaceReturnTypeMismatch',
                    'message' => "{$class_name}::{$method_name}() return type '{$impl_ret}' is not compatible with {$iface_name}::{$method_name}() return type '{$iface_ret}'",
                    'line'    => 0,
                ];
            }
        } elseif ($iface_ret && !$impl_ret) {
            $errors[] = [
                'type'    => 'MissingReturnType',
                'message' => "{$class_name}::{$method_name}() missing return type ': {$iface_ret}', interface {$iface_name} declares it",
                'line'    => 0,
            ];
        }

        // Parameter checks.
        $iface_params = $iface_method['params'];
        $impl_params = $impl_method['params'];
        $min_len = min(count($iface_params), count($impl_params));

        for ($i = 0; $i < $min_len; $i++) {
            $ip = $iface_params[$i];
            $im = $impl_params[$i];

            if ($ip['type'] && !$im['type']) {
                $errors[] = [
                    'type'    => 'MissingParamType',
                    'message' => "{$class_name}::{$method_name}() parameter \${$ip['name']} missing type hint, interface declares '{$ip['type']}'",
                    'line'    => 0,
                ];
            }
        }

        return $errors;
    }
}

if (!function_exists('wp_eeh_is_covariant')) {
    function wp_eeh_is_covariant($iface_type, $impl_type)
    {
        if ($iface_type === $impl_type) return true;
        if ($iface_type === 'mixed') return true;  // mixed is widest
        // Narrowing from interface to impl is OK; widening is NOT.
        $primitives = ['array', 'string', 'int', 'float', 'bool'];
        if (in_array($iface_type, $primitives, true) && $impl_type === 'mixed') return false;
        if ($iface_type === '?' . $impl_type) return true;  // impl narrows nullable
        return false;
    }
}

// =============================================================================
// Layer 2: Require path validator.
// =============================================================================
if (!function_exists('wp_eeh_check_require_paths')) {
    function wp_eeh_check_require_paths($main_file, $plugin_dir)
    {
        $errors = [];
        if (!is_file($main_file)) return $errors;

        $source = @file_get_contents($main_file);
        if ($source === false) return $errors;

        $clean = preg_replace('!//.*$!m', '', $source);
        $clean = preg_replace('!#.*$!m', '', $clean);
        $clean = preg_replace('!/\*.*?\*/!s', '', $clean);

        if (!preg_match_all(
            '/(?:require_once|require|include_once|include)\s*'
            . '(?:(\$\w+|\w+)\s*\.\s*)?'
            . "([\\'\"])([^\\'\"]+)\2/s",
            $clean,
            $matches,
            PREG_SET_ORDER
        )) {
            return $errors;
        }

        $main_dir = dirname($main_file);

        foreach ($matches as $m) {
            $base_var = $m[1] ?? '';
            $rel = $m[3];
            if (strpos($rel, '$') !== false) continue;

            if ($base_var === '__DIR__') {
                $target = $main_dir . '/' . ltrim($rel, '/');
            } else {
                $target = $plugin_dir . '/' . ltrim($rel, '/');
            }
            $target = str_replace('//', '/', $target);

            if (!file_exists($target)) {
                $line = substr_count(substr($source, 0, strpos($source, $m[0])), "\n") + 1;
                $errors[] = [
                    'type'     => 'MissingRequirePath',
                    'message'  => 'require_once target does not exist: ' . $rel,
                    'file'     => basename($main_file),
                    'line'     => $line,
                    'expected' => $target,
                ];
            }
        }

        return $errors;
    }
}

// =============================================================================
// Layer 4: Runtime shutdown handler.
// =============================================================================
if (!function_exists('wp_eeh_shutdown_handler')) {
    function wp_eeh_shutdown_handler()
    {
        $e = error_get_last();
        if (!$e) return;

        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if (!in_array($e['type'], $fatal_types, true)) return;

        $type_name = wp_eeh_error_type_name($e['type']);
        $message   = $e['message'];
        $file      = $e['file'];
        $line      = $e['line'];

        $trace = '';
        if (function_exists('debug_backtrace')) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
            foreach ($bt as $i => $frame) {
                $trace .= sprintf(
                    "#%d %s%s%s(%s)\n",
                    $i,
                    $frame['class'] ?? '',
                    $frame['type'] ?? '',
                    $frame['function'] ?? '<unknown>',
                    isset($frame['file']) ? ' at ' . $frame['file'] . ':' . ($frame['line'] ?? '?') : ''
                );
            }
        }

        if (function_exists('error_log')) {
            error_log(sprintf('[wp-eeh] shutdown %s: %s in %s:%d', $type_name, $message, $file, $line));
        }

        // Persist the single error too.
        $opts = $GLOBALS['wp_eeh_config'] ?? [];
        if ($opts) {
            wp_eeh_persist_errors([[
                'type'    => $type_name,
                'message' => $message,
                'file'    => $file,
                'line'    => $line,
            ]], 'shutdown', $opts);
        }

        $plugin_name = $GLOBALS['wp_eeh_config']['plugin_name'] ?? 'WordPress Plugin';
        wp_eeh_render_single_error($type_name, $message, $file, $line, $trace, $plugin_name);
    }
}

// =============================================================================
// Layer 5: Error persistence.
// =============================================================================
if (!function_exists('wp_eeh_persist_errors')) {
    function wp_eeh_persist_errors($errors, $source, $opts)
    {
        $data = [
            'errors'  => $errors,
            'source'  => $source,
            'plugin'  => $opts['plugin_name'] ?? 'WordPress Plugin',
            'time'    => date('Y-m-d H:i:s'),
            'url'     => $_SERVER['REQUEST_URI'] ?? '',
            'php_ver' => PHP_VERSION,
        ];

        if (function_exists('error_log')) {
            foreach ($errors as $err) {
                $msg = sprintf(
                    '[wp-eeh][%s] %s: %s in %s:%s',
                    $source,
                    $err['type'] ?? 'Unknown',
                    $err['message'] ?? '',
                    $err['file'] ?? '',
                    $err['line'] ?? '?'
                );
                if (isset($err['expected'])) {
                    $msg .= ' (expected: ' . $err['expected'] . ')';
                }
                error_log($msg);
            }
        }

        if ($opts['persist_errors'] ?? false) {
            $option_name = $opts['option_name'] ?? 'wp_eeh_last_errors';
            if (function_exists('update_option')) {
                update_option($option_name, $data, false);
            }
        }

        if (defined('WP_CONTENT_DIR') && !function_exists('update_option')) {
            $log_file = WP_CONTENT_DIR . '/wp-eeh-errors.log';
            @file_put_contents($log_file, json_encode($data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        }
    }
}

// =============================================================================
// Admin notice for persisted errors.
// =============================================================================
if (!function_exists('wp_eeh_admin_notice_persisted_errors')) {
    function wp_eeh_admin_notice_persisted_errors()
    {
        $option_name = $GLOBALS['wp_eeh_config']['option_name'] ?? 'wp_eeh_last_errors';
        if (!function_exists('get_option')) return;

        $data = get_option($option_name);
        if (empty($data) || empty($data['errors'])) return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !in_array($screen->base, ['plugins', 'dashboard'], true)) return;

        $errors = $data['errors'];
        $plugin = $data['plugin'] ?? 'Plugin';
        $count = count($errors);
        $time = $data['time'] ?? '';

        echo '<div class="notice notice-error" style="padding:16px 20px;">';
        echo '<p><strong>' . htmlspecialchars($plugin) . ' — Activation Error</strong> ';
        echo $count . ' error(s) detected at ' . htmlspecialchars($time) . '</p>';

        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<p style="margin:8px 0;padding:8px 12px;background:#fef7f7;border-left:3px solid #d63638;font-family:monospace;font-size:12px;">';
            echo '<strong>#' . $n . '</strong> ';
            echo '<span style="color:#d63638;">[' . htmlspecialchars($err['type'] ?? 'Error') . ']</span> ';
            echo htmlspecialchars($err['message'] ?? '');
            echo '<br>File: <code>' . htmlspecialchars($err['file'] ?? '') . ':' . htmlspecialchars($err['line'] ?? '?') . '</code>';
            echo '</p>';
        }

        echo '<p style="font-size:12px;color:#646970;">';
        echo 'Full details in <code>wp-content/debug.log</code>.';
        echo '</p>';
        echo '</div>';

        delete_option($option_name);
    }
}

// =============================================================================
// Rendering helpers.
// =============================================================================
if (!function_exists('wp_eeh_render_batch_errors')) {
    function wp_eeh_render_batch_errors($errors, $plugin_name)
    {
        $count = count($errors);
        $is_ajax = wp_eeh_is_ajax();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($is_ajax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'data'    => ['error_count' => $count, 'errors' => $errors],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($plugin_name) . ' — Batch Errors</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;color:#3c434a;margin:0;padding:40px 20px;}';
        echo '.wrap{max-width:1100px;margin:0 auto;}';
        echo '.header{background:#d63638;color:#fff;padding:24px 30px;border-radius:4px 4px 0 0;}';
        echo '.header h1{margin:0;font-size:22px;font-weight:600;}';
        echo '.header .count{display:inline-block;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:13px;margin-left:10px;}';
        echo '.body{background:#fff;border:1px solid #dcdcde;border-top:none;padding:24px 30px;border-radius:0 0 4px 4px;}';
        echo '.intro{color:#646970;font-size:14px;margin:0 0 20px;line-height:1.6;}';
        echo '.error-card{background:#fff;border:1px solid #dcdcde;border-left:4px solid #d63638;padding:16px 20px;margin-bottom:14px;border-radius:3px;}';
        echo '.error-card .num{display:inline-block;background:#d63638;color:#fff;width:24px;height:24px;border-radius:50%;text-align:center;line-height:24px;font-size:12px;font-weight:600;margin-right:10px;}';
        echo '.error-card .type{display:inline-block;background:#fef7f7;color:#d63638;padding:2px 8px;border-radius:3px;font-size:12px;font-family:monospace;margin-right:8px;}';
        echo '.error-card .msg{display:block;margin:8px 0 6px;font-family:monospace;font-size:13px;color:#1d2327;line-height:1.5;}';
        echo '.error-card .loc{font-family:monospace;font-size:12px;color:#646970;}';
        echo '.error-card .loc .file{color:#2271b1;}';
        echo '.steps{background:#f0f7fc;border:1px solid #c5d9ed;border-radius:3px;padding:16px 20px;margin-top:20px;}';
        echo '.steps h3{margin:0 0 10px;font-size:14px;color:#1d2327;}';
        echo '.steps ol{margin:0;padding-left:20px;color:#3c434a;font-size:13px;line-height:1.7;}';
        echo '.footer{margin-top:24px;color:#646970;font-size:12px;text-align:center;}';
        echo '.footer code{background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:11px;}';
        echo '</style></head><body>';
        echo '<div class="wrap">';
        echo '<div class="header">';
        echo '<h1>' . htmlspecialchars($plugin_name) . ' — Batch Errors</h1>';
        echo '<span class="count">' . $count . ' error' . ($count > 1 ? 's' : '') . ' found</span>';
        echo '</div>';
        echo '<div class="body">';
        echo '<p class="intro">All PHP files were scanned before loading. '
            . 'The following errors were found and must be fixed. '
            . 'This page shows <strong>every</strong> error at once — fix them all, then reload.</p>';

        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<div class="error-card">';
            echo '<span class="num">' . $n . '</span>';
            echo '<span class="type">' . htmlspecialchars($err['type'] ?? 'Error') . '</span>';
            echo '<span class="msg">' . htmlspecialchars($err['message'] ?? '') . '</span>';
            echo '<span class="loc">File: <span class="file">' . htmlspecialchars($err['file'] ?? '') . '</span>:' . (int) ($err['line'] ?? 0) . '</span>';
            echo '</div>';
        }

        echo '<div class="steps">';
        echo '<h3>Next steps</h3>';
        echo '<ol>';
        echo '<li>Open each file listed above and fix the error at the indicated line.</li>';
        echo '<li>For <code>InterfaceReturnTypeMismatch</code>: change the implementation\'s return type to match the interface.</li>';
        echo '<li>For <code>MissingParamType</code>: add the type hint declared in the interface.</li>';
        echo '<li>For <code>ParseError</code>: check for missing semicolons, unbalanced braces/parentheses.</li>';
        echo '<li>After fixing, reload this page — the scan runs on every request until all files pass.</li>';
        echo '<li>Check <code>wp-content/debug.log</code> for additional runtime errors.</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="footer">';
        echo 'Powered by <code>wp-early-error-handler.php v' . WP_EEH_VERSION . '</code>';
        echo '</div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}

if (!function_exists('wp_eeh_render_single_error')) {
    function wp_eeh_render_single_error($type_name, $message, $file, $line, $trace, $plugin_name)
    {
        $is_ajax = wp_eeh_is_ajax();

        if ($is_ajax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'data' => [
                    'error'   => $type_name,
                    'message' => $message,
                    'file'    => $file,
                    'line'    => $line,
                    'trace'   => $trace,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($plugin_name) . ' — Fatal Error</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;color:#3c434a;margin:0;padding:40px 20px;}';
        echo '.wrap{max-width:900px;margin:0 auto;}';
        echo '.header{background:#d63638;color:#fff;padding:24px 30px;border-radius:4px 4px 0 0;}';
        echo '.header h1{margin:0;font-size:22px;font-weight:600;}';
        echo '.body{background:#fff;border:1px solid #dcdcde;border-top:none;padding:24px 30px;border-radius:0 0 4px 4px;}';
        echo '.field{margin-bottom:14px;}';
        echo '.field .label{font-weight:600;color:#1d2327;display:inline-block;width:90px;vertical-align:top;}';
        echo '.field .value{font-family:monospace;font-size:13px;color:#3c434a;display:inline-block;max-width:760px;word-break:break-all;}';
        echo '.trace{background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:14px;font-family:monospace;font-size:12px;line-height:1.6;white-space:pre-wrap;max-height:300px;overflow-y:auto;margin-top:10px;}';
        echo '.steps{background:#f0f7fc;border:1px solid #c5d9ed;border-radius:3px;padding:16px 20px;margin-top:20px;}';
        echo '.steps h3{margin:0 0 10px;font-size:14px;}';
        echo '.steps ol{margin:0;padding-left:20px;font-size:13px;line-height:1.7;color:#3c434a;}';
        echo '.footer{margin-top:24px;color:#646970;font-size:12px;text-align:center;}';
        echo '</style></head><body>';
        echo '<div class="wrap">';
        echo '<div class="header"><h1>' . htmlspecialchars($plugin_name) . ' — Fatal Error</h1></div>';
        echo '<div class="body">';
        echo '<p style="color:#646970;font-size:14px;margin:0 0 20px;">This page replaces the generic WordPress "critical error" page so you can see the actual cause.</p>';

        echo '<div class="field"><span class="label">Type:</span><span class="value">' . htmlspecialchars($type_name) . '</span></div>';
        echo '<div class="field"><span class="label">Message:</span><span class="value">' . htmlspecialchars($message) . '</span></div>';
        echo '<div class="field"><span class="label">File:</span><span class="value">' . htmlspecialchars($file) . ':' . (int) $line . '</span></div>';

        if (!empty($trace)) {
            echo '<div class="field"><span class="label">Stack:</span></div>';
            echo '<div class="trace">' . htmlspecialchars($trace) . '</div>';
        }

        echo '<div class="steps">';
        echo '<h3>Next steps</h3>';
        echo '<ol>';
        echo '<li>Fix the file/line above.</li>';
        echo '<li>If this is a missing-file error, the plugin zip may be incomplete — re-download and reinstall.</li>';
        echo '<li>Check <code>wp-content/debug.log</code> for the full trace.</li>';
        echo '<li>To restore the default WP error page, remove the early error handler require from the plugin main file.</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="footer">Powered by <code>wp-early-error-handler.php v' . WP_EEH_VERSION . '</code></div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}

// =============================================================================
// Utility functions.
// =============================================================================
if (!function_exists('wp_eeh_relpath')) {
    function wp_eeh_relpath($path, $base)
    {
        $rel = substr($path, strlen($base));
        return ltrim($rel, '/\\');
    }
}

if (!function_exists('wp_eeh_find_namespace_line')) {
    function wp_eeh_find_namespace_line($source)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], '<?php') !== false) $php_started = true;
            if (!$php_started) continue;
            if (preg_match('/^\s*namespace\s+[\\\\]?[\w\\\\]+\s*;/', $lines[$i])) return $i;
        }
        return false;
    }
}

if (!function_exists('wp_eeh_find_statement_before_namespace')) {
    function wp_eeh_find_statement_before_namespace($source, $namespace_line)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        for ($i = 0; $i < $namespace_line; $i++) {
            if (strpos($lines[$i], '<?php') !== false) { $php_started = true; continue; }
            if (!$php_started) continue;
            $trimmed = trim($lines[$i]);
            if ($trimmed === '') continue;
            if (strpos($trimmed, '//') === 0) continue;
            if (strpos($trimmed, '#') === 0) continue;
            if (strpos($trimmed, '/*') === 0) continue;
            if (strpos($trimmed, '*') === 0) continue;
            if (substr(rtrim($trimmed), -2) === '*/') continue;
            if (preg_match('/^declare\s*\(/', $trimmed)) continue;
            return $i;
        }
        return false;
    }
}

if (!function_exists('wp_eeh_is_ajax')) {
    function wp_eeh_is_ajax()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) return true;
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        return false;
    }
}

if (!function_exists('wp_eeh_error_type_name')) {
    function wp_eeh_error_type_name($type)
    {
        $map = [
            E_ERROR             => 'E_ERROR (Fatal Error)',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE (Parse Error)',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        ];
        return $map[$type] ?? ('Error type #' . (int) $type);
    }
}
