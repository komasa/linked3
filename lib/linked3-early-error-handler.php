<?php
/**
 * Linked3 AI — Ultra-Early Error Handler (v3 — Activation-Safe Edition)
 *
 * This file MUST be the very first thing loaded by linked3.php (before any
 * require_once that could fail).
 *
 * KEY FIXES IN v3:
 *   - FIXED CRITICAL BUG: v2 called batch_scan() BEFORE its definition.
 *     PHP conditional functions (inside `if (!function_exists())`) are NOT
 *     hoisted, so this caused "Call to undefined function" fatal — which
 *     prevented the batch scan from ever running.
 *   - ACTIVATION-SAFE: WordPress's activate_plugin() uses ob_start()/
 *     ob_end_clean() which discards all output. v3 stores errors in a
 *     transient and displays them via admin_notices on the next page load.
 *   - All function definitions now come BEFORE any calls.
 *
 * @package Linked3
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('LINKED3_EARLY_ERROR_HANDLER_LOADED')) {
    return;
}
define('LINKED3_EARLY_ERROR_HANDLER_LOADED', true);

if (!defined('LINKED3_EARLY_HANDLER_PLUGIN_DIR')) {
    define('LINKED3_EARLY_HANDLER_PLUGIN_DIR', dirname(__DIR__));
}

// =============================================================================
// ALL FUNCTION DEFINITIONS COME FIRST.
// (PHP conditional functions are NOT hoisted — they must be defined before use.)
// =============================================================================

if (!function_exists('linked3_early_error_type_name')) {
    function linked3_early_error_type_name($type)
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
        return isset($map[$type]) ? $map[$type] : ('Error type #' . (int) $type);
    }
}

if (!function_exists('linked3_early_handler_is_ajax')) {
    function linked3_early_handler_is_ajax()
    : bool {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }
        if (function_exists('did_action') && did_action('wp_ajax_')) {
            return true;
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_is_activation')) {
    /**
     * Detect if we're inside WordPress's plugin activation flow.
     * During activation, output is captured by ob_start()/ob_end_clean(),
     * so we must store errors in a transient instead of printing them.
     */
    function linked3_early_handler_is_activation()
    : bool {
        // Check $_GET['action'] == 'activate' on plugins.php
        if (is_admin() && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && $screen->base === 'plugins') {
                if (isset($_GET['action']) && $_GET['action'] === 'activate') {
                    return true;
                }
            }
        }
        // Check if activate_plugin() is in the call stack
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);
        foreach ($bt as $frame) {
            if (isset($frame['function']) && $frame['function'] === 'activate_plugin') {
                return true;
            }
        }
        // Fallback: check request URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, 'plugins.php') !== false && strpos($uri, 'action=activate') !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_relpath')) {
    function linked3_early_handler_relpath($path, $base)
    {
        $rel = substr($path, strlen($base));
        $rel = ltrim($rel, '/\\');
        return $rel;
    }
}

if (!function_exists('linked3_early_handler_find_namespace_line')) {
    function linked3_early_handler_find_namespace_line($source)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        for ($i = 0; $i < count($lines); $i++) {
            if (strpos($lines[$i], '<?php') !== false) {
                $php_started = true;
            }
            if (!$php_started) {
                continue;
            }
            if (preg_match('/^\s*namespace\s+[\\\\]?[\w\\\\]+\s*;/', $lines[$i])) {
                return $i;
            }
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_find_statement_before_namespace')) {
    function linked3_early_handler_find_statement_before_namespace($source, $namespace_line)
    {
        $lines = explode("\n", $source);
        $php_started = false;
        $in_doc_block = false;
        for ($i = 0; $i < $namespace_line; $i++) {
            if (strpos($lines[$i], '<?php') !== false) {
                $php_started = true;
                continue;
            }
            if (!$php_started) {
                continue;
            }
            $trimmed = trim($lines[$i]);
            if ($trimmed === '') {
                continue;
            }
            if ($in_doc_block) {
                if (substr(rtrim($trimmed), -2) === '*/') {
                    $in_doc_block = false;
                }
                continue;
            }
            if (strpos($trimmed, '/**') === 0) {
                if (substr(rtrim($trimmed), -2) !== '*/') {
                    $in_doc_block = true;
                }
                continue;
            }
            if (strpos($trimmed, '/*') === 0) {
                if (substr(rtrim($trimmed), -2) !== '*/') {
                    $in_doc_block = true;
                }
                continue;
            }
            if (strpos($trimmed, '//') === 0) {
                continue;
            }
            if (strpos($trimmed, '#') === 0) {
                continue;
            }
            if (strpos($trimmed, '*') === 0) {
                continue;
            }
            if (substr(rtrim($trimmed), -2) === '*/') {
                continue;
            }
            if (preg_match('/^declare\s*\(/', $trimmed)) {
                continue;
            }
            return $i;
        }
        return false;
    }
}

if (!function_exists('linked3_early_handler_batch_scan')) {
    /**
     * Scan every .php file in $plugin_dir for syntax errors.
     * Returns an array of errors.
     */
    function linked3_early_handler_batch_scan($plugin_dir, $skip_dirs = [])
    {
        $errors = [];

        if (!is_dir($plugin_dir)) {
            return $errors;
        }

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
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            $skip = false;
            foreach ($skip_paths as $skip_path) {
                if (strpos($path, $skip_path) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $source = @file_get_contents($path);
            if ($source === false) {
                continue;
            }

            $rel = linked3_early_handler_relpath($path, $plugin_dir);

            // Method 1: token_get_all with TOKEN_PARSE.
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

            // Method 2: regex check for "use before namespace".
            $ns_line = linked3_early_handler_find_namespace_line($source);
            if ($ns_line !== false) {
                $bad_line = linked3_early_handler_find_statement_before_namespace($source, $ns_line);
                if ($bad_line !== false) {
                    $lines = explode("\n", $source);
                    $bad_content = trim($lines[$bad_line]);
                    $is_use = (strpos($bad_content, 'use ') === 0);
                    $errors[] = [
                        'type'    => $is_use ? 'UseBeforeNamespace' : 'StatementBeforeNamespace',
                        'message' => $is_use
                            ? 'use statement appears before namespace declaration. use must come AFTER namespace. (Namespace is on line ' . ($ns_line + 1) . ')'
                            : 'Statement appears before namespace declaration (line ' . ($ns_line + 1) . '): "' . substr($bad_content, 0, 100) . '". Namespace must be the first statement or after declare().',
                        'file'    => $rel,
                        'line'    => $bad_line + 1,
                    ];
                }
            }
        }

        return $errors;
    }
}

if (!function_exists('linked3_early_handler_tokenize_safe')) {
    /**
     * Tokenize PHP source safely (suppresses ParseError so we can still
     * process files that have syntax errors — those are already reported
     * by the syntax scan).
     */
    function linked3_early_handler_tokenize_safe($source)
    {
        if (!defined('TOKEN_PARSE')) {
            return @token_get_all($source);
        }
        try {
            return @token_get_all($source, TOKEN_PARSE);
        } catch (Throwable $e) {
            return @token_get_all($source);
        }
    }
}

if (!function_exists('linked3_early_handler_extract_symbol_info')) {
    /**
     * Extract namespace, class/trait/interface declarations, use statements,
     * extends/implements clauses from a PHP file using the tokenizer.
     *
     * Returns associative array:
     *   'namespace'    => string|null
     *   'declarations' => [['kind'=>'class|trait|interface', 'name'=>FQCN, 'line'=>int], ...]
     *   'references'   => [['kind'=>'use|extends|implements', 'symbol'=>FQN, 'line'=>int, 'context'=>string], ...]
     */
    function linked3_early_handler_extract_symbol_info($source)
    {
        $tokens = linked3_early_handler_tokenize_safe($source);
        if (empty($tokens)) {
            return ['namespace' => null, 'declarations' => [], 'references' => []];
        }

        $namespace = null;
        $declarations = [];
        $references = [];

        // Track namespace-level use imports: alias => FQCN
        $ns_uses = [];
        // Track whether we're inside a class/trait/interface body
        $body_depth = 0;
        $current_class = null;
        $current_class_uses = []; // class-level use (trait usage)

        $i = 0;
        $n = count($tokens);
        while ($i < $n) {
            $tok = $tokens[$i];
            if (!is_array($tok)) {
                // Track brace depth for body detection
                if ($tok === '{') {
                    $body_depth++;
                } elseif ($tok === '}') {
                    $body_depth--;
                    if ($body_depth === 0) {
                        $current_class = null;
                    }
                }
                $i++;
                continue;
            }

            $id = $tok[0];
            $text = $tok[1];
            $line = $tok[2];

            // namespace declaration
            if ($id === T_NAMESPACE) {
                $name_parts = [];
                $j = $i + 1;
                // skip whitespace
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                // collect namespace name
                while ($j < $n) {
                    $t = $tokens[$j];
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $name_parts[] = $t[1];
                    } elseif (is_array($t) && $t[0] === T_WHITESPACE) {
                        // skip
                    } elseif ($t === ';') {
                        break;
                    } else {
                        break;
                    }
                    $j++;
                }
                if (!empty($name_parts)) {
                    $namespace = implode('', $name_parts);
                    $namespace = str_replace('\\\\', '\\', $namespace);
                }
                $i = $j + 1;
                continue;
            }

            // use statement (namespace-level import)
            if ($id === T_USE && $body_depth === 0) {
                $j = $i + 1;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                // collect the use target
                $target_parts = [];
                $is_group = false;
                $group_prefix = '';
                $group_items = [];
                while ($j < $n) {
                    $t = $tokens[$j];
                    if ($t === '{') {
                        $is_group = true;
                        $group_prefix = implode('', $target_parts);
                        $target_parts = [];
                        $j++;
                        continue;
                    }
                    if ($t === '}') {
                        // end of group
                        $j++;
                        continue;
                    }
                    if ($t === ';') {
                        break;
                    }
                    if ($t === ',') {
                        if ($is_group) {
                            $group_items[] = implode('', $target_parts);
                            $target_parts = [];
                        }
                        $j++;
                        continue;
                    }
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $target_parts[] = $t[1];
                    }
                    $j++;
                }
                if ($is_group) {
                    if (!empty($target_parts)) {
                        $group_items[] = implode('', $target_parts);
                    }
                    foreach ($group_items as $item) {
                        $fqcn = $group_prefix . $item;
                        $fqcn = ltrim(str_replace('\\\\', '\\', $fqcn), '\\');
                        $alias = substr(strrchr($fqcn, '\\'), 1) ?: $fqcn;
                        $ns_uses[$alias] = $fqcn;
                    }
                } else {
                    $fqcn = implode('', $target_parts);
                    $fqcn = ltrim(str_replace('\\\\', '\\', $fqcn), '\\');
                    // check for alias: use Foo\Bar as Baz
                    $alias = substr(strrchr($fqcn, '\\'), 1) ?: $fqcn;
                    // look for "as" keyword
                    $k = $j;
                    while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                        $k++;
                    }
                    if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_AS) {
                        $k++;
                        while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                            $k++;
                        }
                        if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) {
                            $alias = $tokens[$k][1];
                        }
                    }
                    $ns_uses[$alias] = $fqcn;
                }
                $i = $j + 1;
                continue;
            }

            // class-level use (trait usage inside class body)
            if ($id === T_USE && $body_depth > 0 && $current_class !== null) {
                $j = $i + 1;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                $name_parts = [];
                while ($j < $n) {
                    $t = $tokens[$j];
                    if ($t === ';') {
                        break;
                    }
                    if ($t === ',' || $t === '{') {
                        break;
                    }
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $name_parts[] = $t[1];
                    }
                    $j++;
                }
                $name = implode('', $name_parts);
                $name = str_replace('\\\\', '\\', $name);
                $references[] = [
                    'kind'    => 'use',
                    'symbol'  => $name,
                    'line'    => $line,
                    'context' => 'trait use in ' . $current_class,
                ];
                $i = $j + 1;
                continue;
            }

            // class/trait/interface declaration
            if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                $kind_map = [T_CLASS => 'class', T_INTERFACE => 'interface', T_TRAIT => 'trait'];
                $kind = $kind_map[$id];
                $j = $i + 1;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $name = $tokens[$j][1];
                    $fqcn = $namespace ? $namespace . '\\' . $name : $name;
                    $declarations[] = [
                        'kind' => $kind,
                        'name' => $fqcn,
                        'line' => $line,
                    ];
                    $current_class = $fqcn;

                    // check for extends
                    $k = $j + 1;
                    while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                        $k++;
                    }
                    if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_EXTENDS) {
                        $k++;
                        $ext_parts = [];
                        while ($k < $n) {
                            $t = $tokens[$k];
                            if ($t === '{' || $t === ';') {
                                break;
                            }
                            if (is_array($t) && $t[0] === T_IMPLEMENTS) {
                                break;
                            }
                            if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                                $ext_parts[] = $t[1];
                            }
                            $k++;
                        }
                        if (!empty($ext_parts)) {
                            $ext = implode('', $ext_parts);
                            $ext = str_replace('\\\\', '\\', $ext);
                            $references[] = [
                                'kind'    => 'extends',
                                'symbol'  => $ext,
                                'line'    => $line,
                                'context' => "$kind $name extends",
                            ];
                        }
                    }

                    // check for implements
                    if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_IMPLEMENTS) {
                        $k++;
                        $impl_parts = [];
                        while ($k < $n) {
                            $t = $tokens[$k];
                            if ($t === '{') {
                                break;
                            }
                            if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                                $impl_parts[] = $t[1];
                            } elseif ($t === ',') {
                                $impl_parts[] = ',';
                            }
                            $k++;
                        }
                        if (!empty($impl_parts)) {
                            $impl_str = implode('', $impl_parts);
                            $impl_str = str_replace('\\\\', '\\', $impl_str);
                            foreach (explode(',', $impl_str) as $impl) {
                                $impl = trim($impl);
                                if ($impl !== '') {
                                    $references[] = [
                                        'kind'    => 'implements',
                                        'symbol'  => $impl,
                                        'line'    => $line,
                                        'context' => "$kind $name implements",
                                    ];
                                }
                            }
                        }
                    }
                }
                $i = $j + 1;
                continue;
            }

            $i++;
        }

        return [
            'namespace'    => $namespace,
            'declarations' => $declarations,
            'references'   => $references,
            'ns_uses'      => $ns_uses,
        ];
    }
}

if (!function_exists('linked3_early_handler_resolve_fqcn')) {
    /**
     * Resolve a symbol reference to its FQCN given the current namespace
     * and namespace-level use imports.
     */
    function linked3_early_handler_resolve_fqcn($symbol, $namespace, $ns_uses)
    {
        // G2.4 FIX: Check for fully-qualified (\Exception) BEFORE ltrim strips the backslash.
        // The original code did ltrim first, making the strpos check below always false.
        if (strlen($symbol) > 0 && $symbol[0] === '\\') {
            return ltrim($symbol, '\\'); // Global FQCN — e.g. "Exception", "WP_Widget"
        }

        $symbol = ltrim($symbol, '\\');

        // Check if first segment is in use imports
        $parts = explode('\\', $symbol);
        $first = $parts[0];
        if (isset($ns_uses[$first])) {
            $parts[0] = $ns_uses[$first];
            return implode('\\', $parts);
        }

        // Relative to current namespace
        if ($namespace) {
            return $namespace . '\\' . $symbol;
        }
        return $symbol;
    }
}

if (!function_exists('linked3_early_handler_symbol_scan')) {
    /**
     * Scan all PHP files for missing class/trait/interface references.
     * This catches "Trait not found" / "Class not found" errors that
     * the syntax scan (token_get_all with TOKEN_PARSE) cannot detect
     * because those are RUNTIME errors, not PARSE errors.
     *
     * Returns array of errors.
     */
    function linked3_early_handler_symbol_scan($plugin_dir, $skip_dirs = [])
    {
        $errors = [];
        if (!is_dir($plugin_dir)) {
            return $errors;
        }

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

        // Pass 1: collect all defined symbols and all references
        $symtab = []; // FQCN => [file, line, kind]
        $all_refs = []; // [file, line, kind, symbol, fqcn, context, namespace, ns_uses]

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $skip = false;
            foreach ($skip_paths as $skip_path) {
                if (strpos($path, $skip_path) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $source = @file_get_contents($path);
            if ($source === false) {
                continue;
            }

            $rel = linked3_early_handler_relpath($path, $plugin_dir);
            $info = linked3_early_handler_extract_symbol_info($source);
            $namespace = $info['namespace'];
            $ns_uses = isset($info['ns_uses']) ? $info['ns_uses'] : [];

            // Register declarations
            foreach ($info['declarations'] as $decl) {
                $symtab[$decl['name']] = [
                    'file' => $rel,
                    'line' => $decl['line'],
                    'kind' => $decl['kind'],
                ];
            }

            // Collect references
            foreach ($info['references'] as $ref) {
                $fqcn = linked3_early_handler_resolve_fqcn($ref['symbol'], $namespace, $ns_uses);
                $all_refs[] = [
                    'file'      => $rel,
                    'line'      => $ref['line'],
                    'kind'      => $ref['kind'],
                    'symbol'    => $ref['symbol'],
                    'fqcn'      => $fqcn,
                    'context'   => $ref['context'],
                    'namespace' => $namespace,
                ];
            }
        }

        // Pass 2: resolve references against symbol table + PSR-4 autoload
        foreach ($all_refs as $ref) {
            $fqcn = $ref['fqcn'];

            // Only check Linked3-prefixed symbols (skip PHP builtins, WP, vendor)
            if (strpos($fqcn, 'Linked3') !== 0) {
                continue;
            }

            // Check symbol table
            if (isset($symtab[$fqcn])) {
                continue;
            }

            // Try PSR-4 autoload resolution: Linked3\Classes\Foo\Bar => src/Classes/Foo/Bar.php
            // Also handle Linked3_X_Y naming: Linked3\Classes\Foo\Linked3_X_Y => class-linked3-x-y.php
            $relative = substr($fqcn, strlen('Linked3\\'));
            $relative = str_replace('\\', '/', $relative);
            $candidates = [
                $plugin_dir . '/src/' . $relative . '.php',
                $plugin_dir . '/src/' . dirname($relative) . '/class-' . strtolower(str_replace('_', '-', basename($relative))) . '.php',
                $plugin_dir . '/src/' . dirname($relative) . '/trait-' . strtolower(str_replace('_', '-', basename($relative))) . '.php',
                $plugin_dir . '/src/' . dirname($relative) . '/interface-' . strtolower(str_replace('_', '-', basename($relative))) . '.php',
            ];
            $found = false;
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                continue;
            }

            // Report as missing
            $errors[] = [
                'type'    => 'MissingSymbol',
                'message' => ucfirst($ref['kind']) . ' references "' . $fqcn . '" which is not defined anywhere in the plugin. ' .
                             'Context: ' . $ref['context'] . '. ' .
                             'Likely cause: missing `use` import or wrong namespace.',
                'file'    => $ref['file'],
                'line'    => $ref['line'],
            ];
        }

        
        // G3.4: Detect classes that implement interfaces but don't implement all methods
        foreach ($files as $file_path) {
            $code_content = file_get_contents($file_path);
            if ($code_content === false) continue;
            
            // Find class + implements
            if (preg_match_all('/class\s+(\w+).*?implements\s+([\w\\\s,]+)/', $code_content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $cls_name = $m[1];
                    $ifaces = array_map('trim', explode(',', $m[2]));
                    foreach ($ifaces as $iface) {
                        $iface = ltrim($iface, '\\');
                        // Check if interface file exists and has abstract methods
                        $iface_short = substr(strrchr($iface, '\\'), 1) ?: $iface;
                        $iface_lower = strtolower(str_replace('_', '-', $iface_short));
                        
                        // Find the interface file
                        $iface_file = null;
                        $possible_paths = [
                            dirname($file_path) . "/interface-{$iface_lower}.php",
                            dirname($file_path) . "/class-{$iface_lower}.php",
                        ];
                        foreach ($possible_paths as $p) {
                            if (file_exists($p)) { $iface_file = $p; break; }
                        }
                        
                        if ($iface_file) {
                            $iface_code = file_get_contents($iface_file);
                            // Extract abstract method names
                            if (preg_match_all('/public\s+function\s+(\w+)\s*\([^)]*\)\s*;/i', $iface_code, $iface_methods)) {
                                foreach ($iface_methods[1] as $method_name) {
                                    // Check if class implements this method
                                    if (!preg_match('/function\s+' . preg_quote($method_name) . '\s*\(/i', $code_content)) {
                                        $errors[] = [
                                            'type'    => 'UnimplementedMethod',
                                            'message' => "Class {$cls_name} implements {$iface} but does not implement {$iface}::{$method_name}(). This will cause a Fatal Error at class load time.",
                                            'file'    => $file_path,
                                            'line'    => 0,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // G3.4: Detect bare class names in add_action at file scope
        // ['Linked3_Foo', 'method'] at file scope won't autoload because
        // the autoloader only handles namespaced (Linked3\*) symbols
        foreach ($files as $file_path) {
            $code_content = file_get_contents($file_path);
            if ($code_content === false) continue;
            
            $lines = explode("\n", $code_content);
            $brace_depth = 0;
            foreach ($lines as $i => $line) {
                $brace_depth += substr_count($line, '{') - substr_count($line, '}');
                // Check for bare Linked3_ class refs in add_action at file scope
                if (preg_match('/add_(action|filter)\s*\(.*\[\s*[\'"]Linked3_/', $line) 
                    && !preg_match('/\\\\Linked3/', $line)
                    && $brace_depth <= 0) {
                    $errors[] = [
                        'type'    => 'BareClassRef',
                        'message' => 'add_action/add_filter uses bare class name at file scope. The autoloader only handles namespaced (Linked3\\*) symbols. Use FQCN: [\'\\Namespace\\Linked3_ClassName\', \'method\'].',
                        'file'    => $file_path,
                        'line'    => $i + 1,
                    ];
                }
            }
        }
        
        // G3.4: Detect file-scope __CLASS__ in add_action/add_filter
        // __CLASS__ at file scope (outside class body) resolves to empty string
        // causing "class __CLASS__ not found" Fatal Error
        foreach ($files as $file_path) {
            $code = file_get_contents($file_path);
            if ($code === false) continue;
            
            $lines = explode("\n", $code);
            $brace_depth = 0;
            foreach ($lines as $i => $line) {
                $brace_depth += substr_count($line, '{') - substr_count($line, '}');
                if (preg_match('/add_(action|filter)\s*\(.*\[\s*[\'"]__CLASS__[\'"]\s*,/', $line)) {
                    if ($brace_depth <= 0) {
                        // File scope — __CLASS__ won't resolve!
                        $errors[] = [
                            'type'    => 'FileScopeMagicConst',
                            'message' => 'add_action/add_filter uses __CLASS__ at file scope (outside class body). __CLASS__ resolves to empty string, causing Fatal Error. Use the FQCN string instead, e.g. [\'\\Linked3\\NS\\ClassName\', \'method\'].',
                            'file'    => $file_path,
                            'line'    => $i + 1,
                        ];
                    }
                }
            }
        }
        
return $errors;
    }
}

if (!function_exists('linked3_early_handler_store_errors')) {
    /**
     * Store errors in a transient so they survive redirects (e.g. during
     * plugin activation, where output is captured by ob_start/ob_end_clean).
     */
    function linked3_early_handler_store_errors($errors, $source = 'batch_scan')
    : void {
        if (empty($errors)) {
            return;
        }
        $data = [
            'source'   => $source,
            'time'     => time(),
            'errors'   => $errors,
            'count'    => count($errors),
            'plugin'   => 'Linked3 AI',
            'version'  => defined('LINKED3_VERSION') ? LINKED3_VERSION : 'unknown',
        ];
        // Use both transient and option for redundancy.
        if (function_exists('set_transient')) {
            set_transient('linked3_activation_errors', $data, 3600);
        }
        // Also store in option (persists longer, survives cache clears).
        if (function_exists('update_option')) {
            update_option('linked3_last_errors', $data, false);
        }
        // Also store in global for in-request access.
        $GLOBALS['linked3_early_errors'] = $errors;
        $GLOBALS['linked3_early_errors_meta'] = $data;
    }
}

if (!function_exists('linked3_early_handler_render_batch_errors')) {
    function linked3_early_handler_render_batch_errors($errors)
    : void {
        $count = count($errors);
        $is_ajax = linked3_early_handler_is_ajax();

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
                'data'    => [
                    'error_count' => $count,
                    'errors'      => $errors,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Linked3 AI — Batch Syntax Errors</title>';
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
        echo '<h1>Linked3 AI — Batch Syntax Errors</h1>';
        echo '<span class="count">' . $count . ' error' . ($count > 1 ? 's' : '') . ' found</span>';
        echo '</div>';
        echo '<div class="body">';
        echo '<p class="intro">All PHP files in the plugin were scanned before loading. '
            . 'The following file(s) have syntax errors and must be fixed. '
            . 'This page shows <strong>every</strong> broken file at once — fix them all, then reload.</p>';

        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<div class="error-card">';
            echo '<span class="num">' . $n . '</span>';
            echo '<span class="type">' . htmlspecialchars($err['type']) . '</span>';
            echo '<span class="msg">' . htmlspecialchars($err['message']) . '</span>';
            echo '<span class="loc">File: <span class="file">' . htmlspecialchars($err['file']) . '</span>:' . (int) $err['line'] . '</span>';
            echo '</div>';
        }

        echo '<div class="steps">';
        echo '<h3>Next steps</h3>';
        echo '<ol>';
        echo '<li>Open each file listed above and fix the syntax error at the indicated line.</li>';
        echo '<li>For <code>UseBeforeNamespace</code> errors: move the <code>namespace</code> declaration to be the first statement (right after <code>&lt;?php</code>), then put <code>use</code> statements after it.</li>';
        echo '<li>For <code>ParseError</code> errors: check for missing semicolons, unbalanced braces/parentheses, or typos.</li>';
        echo '<li>After fixing, reload this page — the scan runs on every request until all files pass.</li>';
        echo '<li>Check <code>wp-content/debug.log</code> for additional runtime errors.</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="footer">';
        echo 'Powered by <code>linked3-early-error-handler.php v3</code> — batch syntax scan + runtime shutdown handler + activation-safe transient storage.';
        echo '</div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}

if (!function_exists('linked3_early_handler_render_single_error')) {
    function linked3_early_handler_render_single_error($type_name, $message, $file, $line, $trace)
    : void {
        $is_ajax = linked3_early_handler_is_ajax();

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
        echo '<title>Linked3 AI — Fatal Error</title>';
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
        echo '<div class="header"><h1>Linked3 AI — Fatal Error (real error shown)</h1></div>';
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

        echo '<div class="footer">Powered by <code>linked3-early-error-handler.php v3</code></div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}

if (!function_exists('linked3_early_handler_shutdown')) {
    function linked3_early_handler_shutdown()
    : void {
        $e = error_get_last();
        if (!$e) {
            return;
        }

        $fatal_types = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR,
        ];
        if (!in_array($e['type'], $fatal_types, true)) {
            return;
        }

        $type_name = linked3_early_error_type_name($e['type']);
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
                    isset($frame['class']) ? $frame['class'] : '',
                    isset($frame['type']) ? $frame['type'] : '',
                    isset($frame['function']) ? $frame['function'] : '<unknown>',
                    isset($frame['file']) ? ' at ' . $frame['file'] . ':' . ($frame['line'] ?? '?') : ''
                );
            }
        }

        if (function_exists('error_log')) {
            error_log(sprintf('[linked3] shutdown %s: %s in %s:%d', $type_name, $message, $file, $line));
        }

        // Store in transient for activation-safe display.
        linked3_early_handler_store_errors([
            [
                'type'    => $type_name,
                'message' => $message,
                'file'    => $file,
                'line'    => $line,
                'trace'   => $trace,
            ]
        ], 'shutdown_fatal');

        // Render the error page (works for non-activation requests).
        linked3_early_handler_render_single_error($type_name, $message, $file, $line, $trace);
    }
}

if (!function_exists('linked3_early_handler_admin_notice')) {
    /**
     * Display stored errors as an admin notice. This fires on the NEXT page
     * load after activation fails (because activation output is captured by
     * ob_start/ob_end_clean and discarded).
     */
    function linked3_early_handler_admin_notice()
    : void {
        $data = null;

        // Check transient first.
        if (function_exists('get_transient')) {
            $data = get_transient('linked3_activation_errors');
            if ($data) {
                delete_transient('linked3_activation_errors');
            }
        }

        // Fallback to option.
        if (!$data && function_exists('get_option')) {
            $data = get_option('linked3_last_errors');
            if ($data) {
                delete_option('linked3_last_errors');
            }
        }

        if (empty($data) || empty($data['errors'])) {
            return;
        }

        $errors = $data['errors'];
        $count = count($errors);
        $source = isset($data['source']) ? $data['source'] : 'unknown';
        $plugin = isset($data['plugin']) ? $data['plugin'] : 'Linked3 AI';

        echo '<div class="notice notice-error" style="padding:20px;">';
        echo '<h3 style="margin-top:0;color:#d63638;">' . esc_html($plugin) . ' — ' . $count . ' Error' . ($count > 1 ? 's' : '') . ' Detected</h3>';
        echo '<p style="color:#646970;font-size:13px;margin-bottom:16px;">';
        echo 'Source: <code>' . esc_html($source) . '</code>. ';
        echo 'These errors were captured during plugin load and stored for display (because WordPress suppresses output during activation).';
        echo '</p>';

        echo '<div style="background:#fef7f7;border:1px solid #d63638;border-radius:3px;padding:16px;">';
        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #f0f0f1;">';
            echo '<div style="font-family:monospace;font-size:12px;color:#d63638;margin-bottom:4px;">';
            echo '<strong>[' . $n . ']</strong> ' . esc_html($err['type']);
            echo '</div>';
            echo '<div style="font-family:monospace;font-size:13px;color:#1d2327;margin-bottom:6px;">';
            echo esc_html($err['message']);
            echo '</div>';
            echo '<div style="font-family:monospace;font-size:12px;color:#646970;">';
            echo 'File: <strong style="color:#2271b1;">' . esc_html($err['file']) . '</strong>:' . (int) $err['line'];
            echo '</div>';
            if (!empty($err['trace'])) {
                echo '<details style="margin-top:8px;">';
                echo '<summary style="cursor:pointer;font-size:12px;color:#2271b1;">Show stack trace</summary>';
                echo '<pre style="background:#f6f7f7;padding:10px;font-size:11px;overflow-x:auto;max-height:200px;">' . esc_html($err['trace']) . '</pre>';
                echo '</details>';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<p style="margin-top:16px;font-size:13px;">';
        echo '<strong>Next steps:</strong> Fix the file(s) above, then deactivate and reactivate the plugin. ';
        echo 'Check <code>wp-content/debug.log</code> for more details.';
        echo '</p>';

        echo '</div>';
    }
}

// =============================================================================
// MAIN LOGIC — runs after all functions are defined.
// =============================================================================

// 1. Force PHP to surface every error.
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    if (defined('WP_CONTENT_DIR')) {
        @ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
    }
    error_reporting(E_ALL);
}

// 2. Disable WordPress's fatal error handler.
if (!defined('WP_FATAL_ERROR_HANDLER_ENABLED')) {
    define('WP_FATAL_ERROR_HANDLER_ENABLED', false);
}
if (!defined('WP_DISABLE_FATAL_ERROR_HANDLER')) {
    define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
}
if (function_exists('add_filter')) {
    add_filter('wp_fatal_error_handler_enabled', '__return_false', 1);
}

// 3. Register admin_notices hook for activation-safe error display.
//    This fires on the NEXT page load after activation fails.
if (function_exists('add_action')) {
    add_action('admin_notices', 'linked3_early_handler_admin_notice', 1);
    add_action('network_admin_notices', 'linked3_early_handler_admin_notice', 1);
}

// 4. BATCH SYNTAX SCAN — find ALL broken PHP files before loading anything.
//    (Functions are now defined above, so this call works.)
$linked3_scan_errors = linked3_early_handler_batch_scan(
    LINKED3_EARLY_HANDLER_PLUGIN_DIR,
    ['node_modules', '.git', 'vendor', 'tests/bin', 'lib', 'assets', 'admin', 'languages', 'scripts', 'docs']
);

// 4b. BATCH SYMBOL RESOLUTION SCAN — find ALL missing class/trait/interface
//     references. This catches "Trait not found" / "Class not found" errors
//     that the syntax scan cannot detect (those are RUNTIME errors, not
//     PARSE errors). By scanning in batch we surface ALL of them at once
//     instead of dying on the first one.
$linked3_symbol_errors = linked3_early_handler_symbol_scan(
    LINKED3_EARLY_HANDLER_PLUGIN_DIR,
    ['node_modules', '.git', 'vendor', 'tests/bin', 'lib', 'assets', 'admin', 'languages', 'scripts', 'docs']
);

// Merge symbol errors into the scan errors for unified batch display.
if (!empty($linked3_symbol_errors)) {
    $linked3_scan_errors = array_merge($linked3_scan_errors, $linked3_symbol_errors);
}

if (!empty($linked3_scan_errors)) {
    // Store in transient for activation-safe display.
    linked3_early_handler_store_errors($linked3_scan_errors, 'batch_scan');

    // Render the error page and stop execution.
    // For activation requests, the output may be captured by ob, but the
    // transient will be displayed via admin_notices on the next page load.
    linked3_early_handler_render_batch_errors($linked3_scan_errors);
    exit;
}

// 5. Register shutdown handler for runtime fatals.
register_shutdown_function('linked3_early_handler_shutdown');
