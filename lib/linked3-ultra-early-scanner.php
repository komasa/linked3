<?php
/**
 * Linked3 Ultra-Early Batch Error Scanner
 * ========================================
 *
 * Loaded at the VERY TOP of linked3.php — before any business require_once.
 * Performs a static scan of ALL .php files in the plugin directory and
 * collects every issue it can find, then stores results in
 * $GLOBALS['linked3_early_errors'] for the wp-early-error-handler to render.
 *
 * KEY DESIGN PRINCIPLE: "No batch reporting = whack-a-mole debugging."
 * PHP fatal errors HALT execution — you can only see ONE at a time.
 * This scanner finds ALL structural issues in one pass so the developer
 * sees the complete picture, not just the first failure.
 *
 * CHECKS PERFORMED
 * ----------------
 *   1. Namespace-internal duplicate function declarations
 *      + function_exists guard name mismatch (bare-name vs FQN)
 *   2. PHP syntax structure static validation
 *      (braces, parentheses, brackets, string delimiters,
 *       use-before-namespace, missing semicolons in declarations)
 *   3. add_action / add_filter callback class existence check
 *      (static method callbacks referencing classes that don't exist)
 *   4. Cross-file duplicate function / class definitions
 *      (same FQN declared in two different files)
 *
 * INTEGRATION
 * -----------
 *   - Results written to $GLOBALS['linked3_early_errors']
 *   - If wp-early-error-handler.php is loaded, errors are rendered via
 *     its batch error page (all errors at once, not first-only).
 *   - If not, a minimal standalone renderer kicks in.
 *   - The scanner is self-contained: no external dependencies, no
 *     function calls to WordPress APIs (runs before WP is available).
 *
 * @version   1.0.0
 * @since     27.6.9
 * @license   GPL-2.0-or-later
 * @package   Linked3
 */

// Prevent double-load.
if (defined('LINKED3_UES_LOADED')) {
    return;
}
define('LINKED3_UES_LOADED', true);
define('LINKED3_UES_VERSION', '1.0.0');

/**
 * Run the ultra-early scanner.
 *
 * @param string $plugin_dir  Root directory of the plugin.
 * @return array              Array of error arrays.
 */
if (!function_exists('linked3_ues_scan')) {
    function linked3_ues_scan($plugin_dir)
    {
        $errors = [];

        if (!is_dir($plugin_dir)) {
            return $errors;
        }

        // ── Collect all PHP files ──────────────────────────────────────
        $php_files = linked3_ues_collect_php_files($plugin_dir);
        if (empty($php_files)) {
            return $errors;
        }

        // ── Parse every file: extract namespace, functions, classes, hooks ──
        $file_infos = [];      // path => parsed info
        $function_fqn_map = []; // FQN => [file, line]  (for cross-file dup check)
        $class_fqn_map = [];    // FQN => [file, line]
        $hook_callbacks = [];   // [tag, callback_spec, file, line]

        foreach ($php_files as $path) {
            $info = linked3_ues_parse_file($path, $plugin_dir);
            if ($info === null) {
                continue;
            }
            $file_infos[$path] = $info;

            // Collect functions by FQN for cross-file duplicate detection.
            foreach ($info['functions'] as $fname => $fdata) {
                $fqn = $info['namespace'] ? $info['namespace'] . '\\' . $fname : $fname;
                if (isset($function_fqn_map[$fqn])) {
                    $prev = $function_fqn_map[$fqn];
                    $errors[] = [
                        'type'    => 'DuplicateFunctionDeclaration',
                        'message' => "Function '{$fqn}' is declared in multiple files: "
                            . $prev['file'] . ':' . $prev['line']
                            . ' and ' . $info['rel_path'] . ':' . $fdata['line'],
                        'file'    => $info['rel_path'],
                        'line'    => $fdata['line'],
                    ];
                } else {
                    $function_fqn_map[$fqn] = [
                        'file' => $info['rel_path'],
                        'line' => $fdata['line'],
                    ];
                }
            }

            // Collect classes by FQN for cross-file duplicate detection.
            foreach ($info['classes'] as $cname => $cdata) {
                $fqn = $info['namespace'] ? $info['namespace'] . '\\' . $cname : $cname;
                if (isset($class_fqn_map[$fqn])) {
                    $prev = $class_fqn_map[$fqn];
                    $errors[] = [
                        'type'    => 'DuplicateClassDeclaration',
                        'message' => "Class/Interface '{$fqn}' is declared in multiple files: "
                            . $prev['file'] . ':' . $prev['line']
                            . ' and ' . $info['rel_path'] . ':' . $cdata['line'],
                        'file'    => $info['rel_path'],
                        'line'    => $cdata['line'],
                    ];
                } else {
                    $class_fqn_map[$fqn] = [
                        'file' => $info['rel_path'],
                        'line' => $cdata['line'],
                    ];
                }
            }

            // Collect add_action/add_filter callbacks.
            foreach ($info['hooks'] as $hook) {
                $hook_callbacks[] = $hook;
            }
        }

        // ── Check 1: function_exists guard name mismatch (namespace bug) ──
        foreach ($file_infos as $path => $info) {
            $ns = $info['namespace'];
            if (!$ns) {
                continue; // Global namespace — bare-name guard is correct.
            }
            foreach ($info['function_exists_guards'] as $guard) {
                $bare_name = $guard['function_name'];
                // Does a function with this bare name actually exist in this namespace?
                $fqn = $ns . '\\' . $bare_name;
                if (isset($function_fqn_map[$fqn])) {
                    // The function is declared in this namespace, but the guard
                    // checks the GLOBAL bare name. This is a bug — the guard
                    // will NEVER match the namespaced function, so if another
                    // file in the same namespace also declares it, PHP fatals.
                    $errors[] = [
                        'type'    => 'NamespaceGuardMismatch',
                        'message' => "function_exists('{$bare_name}') checks the GLOBAL scope, "
                            . "but the function is declared in namespace '{$ns}' as '{$fqn}'. "
                            . "The guard will never match. Use function_exists(__NAMESPACE__.'\\\\{$bare_name}') instead.",
                        'file'    => $info['rel_path'],
                        'line'    => $guard['line'],
                    ];
                }
            }
        }

        // ── Check 2: PHP syntax structure (braces/parens/brackets/strings) ──
        // Note: This is a lightweight static check. We can't use token_get_all
        // because we're running before PHP's tokenizer might not be available
        // in all environments. We use a string-scanning approach.
        foreach ($file_infos as $path => $info) {
            $syntax_errors = linked3_ues_check_syntax_structure($path, $info);
            foreach ($syntax_errors as $se) {
                $errors[] = $se;
            }
        }

        // ── Check 3: add_action/add_filter callback class existence ──
        // Build a set of all known class FQNs (including global-name aliases).
        $all_class_names = [];
        foreach ($class_fqn_map as $fqn => $loc) {
            $all_class_names[$fqn] = true;
            // Also store the short name (last part after \).
            $short = substr(strrchr($fqn, '\\'), 1) ?: $fqn;
            if (!isset($all_class_names[$short])) {
                $all_class_names[$short] = true;
            }
        }
        // Also collect class names from use-statement aliases.
        foreach ($file_infos as $path => $info) {
            foreach ($info['use_map'] as $alias => $fqn) {
                $all_class_names[$alias] = true;
            }
        }

        foreach ($hook_callbacks as $cb) {
            $callback = $cb['callback'];
            if (!is_array($callback) || count($callback) !== 2) {
                continue;
            }
            $class_ref = $callback[0];
            if (!is_string($class_ref) || $class_ref === '' || $class_ref === '__CLASS__') {
                continue; // __CLASS__ is handled by the runtime interceptor.
            }

            // Resolve the class reference.
            $resolved = $class_ref;
            // Strip leading backslash.
            $test_name = ltrim($class_ref, '\\');

            // Check if it exists in our class table.
            $found = isset($all_class_names[$test_name])
                || isset($all_class_names[$class_ref]);

            // Try use_map resolution from the declaring file.
            if (!$found && isset($file_infos[$cb['file']])) {
                $use_map = $file_infos[$cb['file']]['use_map'];
                if (isset($use_map[$test_name])) {
                    $resolved_fqn = $use_map[$test_name];
                    $found = isset($all_class_names[$resolved_fqn]);
                }
            }

            // Try namespace-relative resolution.
            if (!$found && isset($file_infos[$cb['file']])) {
                $ns = $file_infos[$cb['file']]['namespace'];
                if ($ns) {
                    $candidate = $ns . '\\' . $test_name;
                    $found = isset($all_class_names[$candidate]);
                }
            }

            if (!$found) {
                $errors[] = [
                    'type'    => 'HookCallbackClassNotFound',
                    'message' => "add_{$cb['type']}('{$cb['tag']}', ['{$class_ref}', '{$callback[1]}']) "
                        . "— class '{$class_ref}' not found in any scanned file",
                    'file'    => $cb['rel_path'],
                    'line'    => $cb['line'],
                ];
            }
        }

        return $errors;
    }
}

/**
 * Collect all PHP files in a directory tree, excluding common non-source dirs.
 */
if (!function_exists('linked3_ues_collect_php_files')) {
    function linked3_ues_collect_php_files($dir, $skip_dirs = null)
    {
        $skip_dirs = $skip_dirs ?: ['node_modules', '.git', 'vendor', 'tests/bin', 'assets', 'languages'];
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
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $skip = false;
            foreach ($skip_paths as $sp) {
                if (strpos($path, $sp) === 0) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip) {
                $files[] = $path;
            }
        }

        return $files;
    }
}

/**
 * Extract file-scope function declarations from PHP source.
 *
 * This distinguishes between:
 *   function my_func() { ... }       ← file-scope function
 *   class Foo { function bar() {} }  ← class method (NOT counted)
 *   <script> function post() {} </script>  ← JavaScript (NOT counted)
 *
 * Approach:
 *   1. Strip non-PHP content (outside <?php ?> tags) — preserves line numbers.
 *   2. Find all class/interface/trait body ranges (opening brace to matching close).
 *   3. Find all `function name(` matches.
 *   4. Only keep those NOT inside any class body range.
 *
 * @param string $source  Original PHP source (for line number calculation).
 * @param string $clean   Comment/string-stripped source.
 * @return array          function_name => ['line' => int]
 */
if (!function_exists('linked3_ues_extract_file_scope_functions')) {
    function linked3_ues_extract_file_scope_functions($source, $clean)
    {
        $functions = [];

        // ── Step 0: Strip non-PHP content (HTML/JS outside <?php ?> tags) ──
        // Template files interleave PHP and HTML/JS. We must only look at PHP code.
        $php_only = linked3_ues_strip_non_php($clean);

        // ── Step 1: Find all class/interface/trait body ranges ──
        $class_ranges = [];
        $class_pattern = '/\b(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+\w+/';
        $offset = 0;
        while (preg_match($class_pattern, $php_only, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $class_decl_start = $m[0][1];
            $class_decl_end = $class_decl_start + strlen($m[0][0]);

            // Find the opening brace of the class body.
            $brace_pos = strpos($php_only, '{', $class_decl_end);
            if ($brace_pos === false) {
                $offset = $class_decl_end;
                continue;
            }

            // Find the matching closing brace by counting brace depth.
            $depth = 1;
            $pos = $brace_pos + 1;
            $len = strlen($php_only);
            while ($pos < $len && $depth > 0) {
                if ($php_only[$pos] === '{') {
                    $depth++;
                } elseif ($php_only[$pos] === '}') {
                    $depth--;
                }
                $pos++;
            }

            $class_ranges[] = [$brace_pos, $pos];
            $offset = $pos;
        }

        // ── Step 2: Find all function declarations ──
        if (!preg_match_all('/\bfunction\s+(\w+)\s*\(/', $php_only, $matches, PREG_OFFSET_CAPTURE)) {
            return $functions;
        }

        // ── Step 3: Keep only functions NOT inside any class body ──
        foreach ($matches[1] as $m) {
            $fname = $m[0];
            $pos = $m[1];

            $in_class = false;
            foreach ($class_ranges as $range) {
                if ($pos >= $range[0] && $pos < $range[1]) {
                    $in_class = true;
                    break;
                }
            }

            if (!$in_class) {
                $line = substr_count(substr($source, 0, $pos), "\n") + 1;
                $functions[$fname] = ['line' => $line];
            }
        }

        return $functions;
    }
}

/**
 * Strip non-PHP content from source, replacing it with whitespace.
 *
 * PHP template files interleave PHP code (<?php ... ?>) with HTML/JS.
 * This function replaces everything outside <?php ?> tags with spaces,
 * preserving newlines so line numbers remain accurate.
 *
 * This prevents the scanner from mistaking JavaScript functions in
 * <script> blocks for PHP function declarations.
 *
 * @param string $source  PHP source (possibly with embedded HTML/JS).
 * @return string         PHP-only source (non-PHP replaced with spaces).
 */
if (!function_exists('linked3_ues_strip_non_php')) {
    function linked3_ues_strip_non_php($source)
    {
        $result = '';
        $len = strlen($source);
        $i = 0;
        $in_php = false;

        while ($i < $len) {
            if (!$in_php) {
                // Look for <?php or <?= or <? (short tag)
                if (substr($source, $i, 5) === '<?php') {
                    $in_php = true;
                    $result .= '<?php';
                    $i += 5;
                } elseif (substr($source, $i, 3) === '<?=') {
                    $in_php = true;
                    $result .= '<?=';
                    $i += 3;
                } elseif (substr($source, $i, 2) === '<?' && ($i + 2 >= $len || $source[$i + 2] !== 'x')) {
                    // Short open tag <? (but not <?xml)
                    $in_php = true;
                    $result .= '<?';
                    $i += 2;
                } else {
                    // Non-PHP content — replace with space, keep newlines.
                    $result .= ($source[$i] === "\n") ? "\n" : ' ';
                    $i++;
                }
            } else {
                // In PHP mode — look for ?>
                if (substr($source, $i, 2) === '?>') {
                    $in_php = false;
                    $result .= '?>';
                    $i += 2;
                } else {
                    $result .= $source[$i];
                    $i++;
                }
            }
        }

        return $result;
    }
}

/**
 * Parse a single PHP file to extract:
 *   - namespace
 *   - use statement map (alias => FQN)
 *   - declared functions (name => [line])
 *   - declared classes/interfaces/traits (name => [line])
 *   - function_exists() guards (function_name, line)
 *   - add_action/add_filter calls with their callbacks
 */
if (!function_exists('linked3_ues_parse_file')) {
    function linked3_ues_parse_file($filepath, $plugin_dir)
    {
        $source = @file_get_contents($filepath);
        if ($source === false) {
            return null;
        }

        $rel_path = linked3_ues_relpath($filepath, $plugin_dir);

        // ── Extract namespace ──
        $namespace = '';
        if (preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $source, $ns_match)) {
            $namespace = $ns_match[1];
        }

        // ── Extract use statements ──
        $use_map = [];
        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $source, $use_matches, PREG_SET_ORDER)) {
            foreach ($use_matches as $um) {
                $fqn = $um[1];
                $alias = $um[2] ?? (($bs = strrpos($fqn, '\\')) !== false ? substr($fqn, $bs + 1) : $fqn);
                $use_map[$alias] = $fqn;
            }
        }

        // ── Strip comments and strings for reliable symbol detection ──
        // We need a careful approach: remove // comments, # comments, /* */ blocks,
        // and string contents (but keep the delimiters so brace counting works).
        $clean = $source;
        // Remove /* */ block comments.
        $clean = preg_replace('!/\*[\s\S]*?\*/!', '', $clean);
        // Remove // line comments.
        $clean = preg_replace('!//[^\n]*!', '', $clean);
        // Remove # line comments (but not #[Attributes] in PHP 8+).
        $clean = preg_replace('!#(?!\[)[^\n]*!', '', $clean);

        // ── Extract function declarations (file-scope only, NOT class methods) ──
        // We must distinguish between:
        //   function my_func() { ... }    ← file-scope function (at brace depth 0, outside any class)
        //   class Foo { function bar() {} }  ← class method (inside a class body)
        //
        // Approach: find all class/interface/trait body ranges first,
        // then only count functions NOT inside any class body.
        $functions = linked3_ues_extract_file_scope_functions($source, $clean);

        // ── Extract class/interface/trait declarations ──
        $classes = [];
        if (preg_match_all('/\b(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+(\w+)/', $clean, $cls_matches, PREG_OFFSET_CAPTURE)) {
            foreach ($cls_matches[1] as $m) {
                $cname = $m[0];
                $line = substr_count(substr($source, 0, $m[1]), "\n") + 1;
                $classes[$cname] = ['line' => $line];
            }
        }

        // ── Extract function_exists() guards ──
        $guards = [];
        if (preg_match_all('/function_exists\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $clean, $guard_matches, PREG_OFFSET_CAPTURE)) {
            foreach ($guard_matches[1] as $m) {
                $fn_name = $m[0];
                $line = substr_count(substr($source, 0, $m[1]), "\n") + 1;
                $guards[] = [
                    'function_name' => $fn_name,
                    'line'          => $line,
                ];
            }
        }

        // ── Extract add_action / add_filter calls ──
        $hooks = [];
        // Match: add_action( 'tag', callback, ... ) or add_filter( 'tag', callback, ... )
        // The callback can be: 'function_name', [ 'Class', 'method' ], [ Class::class, 'method' ],
        // or a closure.
        $hook_pattern = '/\b(add_action|add_filter)\s*\(\s*'
            . "([\\'\"])([^\\'\"]+)\\2\\s*,\s*"
            . '(.+?)\s*(?:,\s*\d+\s*)?(?:,\s*\d+\s*)?\s*\)/s';

        if (preg_match_all($hook_pattern, $clean, $hook_matches, PREG_SET_ORDER)) {
            foreach ($hook_matches as $hm) {
                $type = ($hm[1] === 'add_action') ? 'action' : 'filter';
                $tag = $hm[3];
                $callback_str = trim($hm[4]);

                // Parse callback.
                $callback = null;

                // Array callback: ['Class', 'method'] or [Class::class, 'method']
                if (preg_match("/^\[(['\"]?)([\w\\\\]+)(?:::class)?\\1\s*,\s*(['\"])(\w+)\\3\]/", $callback_str, $arr_match)) {
                    $callback = [$arr_match[2], $arr_match[4]];
                }
                // String callback: 'function_name'
                elseif (preg_match("/^['\"]([\w\\\\]+)['\"]$/", $callback_str, $str_match)) {
                    $callback = $str_match[1]; // string function name
                }

                if ($callback !== null) {
                    $line = substr_count(substr($source, 0, $hm[0] !== '' ? strpos($source, $hm[0]) : 0), "\n") + 1;
                    // Re-find the position properly.
                    $pos = strpos($source, $hm[0]);
                    $line = $pos !== false ? substr_count(substr($source, 0, $pos), "\n") + 1 : 0;

                    $hooks[] = [
                        'type'     => $type,
                        'tag'      => $tag,
                        'callback' => $callback,
                        'file'     => $filepath,
                        'rel_path' => $rel_path,
                        'line'     => $line,
                    ];
                }
            }
        }

        return [
            'filepath'                => $filepath,
            'rel_path'                => $rel_path,
            'namespace'               => $namespace,
            'use_map'                 => $use_map,
            'functions'               => $functions,
            'classes'                 => $classes,
            'function_exists_guards'  => $guards,
            'hooks'                   => $hooks,
            'source'                  => $source,
            'clean'                   => $clean,
        ];
    }
}

/**
 * Check PHP syntax structure: balanced braces, parentheses, brackets,
 * string delimiters, use-before-namespace, missing semicolons in declarations.
 *
 * This is a lightweight static check — it doesn't replace `php -l` but
 * catches common structural errors without a PHP runtime.
 */
if (!function_exists('linked3_ues_check_syntax_structure')) {
    function linked3_ues_check_syntax_structure($filepath, $info)
    {
        $errors = [];
        $source = $info['source'];
        $rel_path = $info['rel_path'];

        // ── Brace balance ──
        // We need to count braces while ignoring those inside strings and comments.
        // Use the cleaned source (comments removed) but still need to handle strings.
        $clean = $info['clean'];

        // Remove string contents (keep delimiters for balance).
        $no_strings = preg_replace("/'(?:\\\\.|[^'\\\\])*'/s", "''", $clean);
        $no_strings = preg_replace('/"(?:\\\\.|[^"\\\\])*"/s', '""', $no_strings);

        $open_braces = substr_count($no_strings, '{');
        $close_braces = substr_count($no_strings, '}');
        if ($open_braces !== $close_braces) {
            $errors[] = [
                'type'    => 'UnbalancedBraces',
                'message' => "Unbalanced braces: {$open_braces} '{' vs {$close_braces} '}'",
                'file'    => $rel_path,
                'line'    => 0,
            ];
        }

        $open_parens = substr_count($no_strings, '(');
        $close_parens = substr_count($no_strings, ')');
        if ($open_parens !== $close_parens) {
            $errors[] = [
                'type'    => 'UnbalancedParentheses',
                'message' => "Unbalanced parentheses: {$open_parens} '(' vs {$close_parens} ')'",
                'file'    => $rel_path,
                'line'    => 0,
            ];
        }

        $open_brackets = substr_count($no_strings, '[');
        $close_brackets = substr_count($no_strings, ']');
        if ($open_brackets !== $close_brackets) {
            $errors[] = [
                'type'    => 'UnbalancedBrackets',
                'message' => "Unbalanced brackets: {$open_brackets} '[' vs {$close_brackets} ']'",
                'file'    => $rel_path,
                'line'    => 0,
            ];
        }

        // ── Use-before-namespace check ──
        $ns_line = null;
        if (preg_match('/^\s*namespace\s+[\w\\\\]+\s*;/m', $source, $ns_m, PREG_OFFSET_CAPTURE)) {
            $ns_line = substr_count(substr($source, 0, $ns_m[0][1]), "\n") + 1;
        }

        if ($ns_line !== null && $ns_line > 1) {
            // Check if there's a `use` statement before the namespace declaration.
            $before_ns = substr($source, 0, $ns_m[0][1]);
            // Remove comments from the pre-namespace section.
            $before_ns_clean = preg_replace('!/\*[\s\S]*?\*/!', '', $before_ns);
            $before_ns_clean = preg_replace('!//[^\n]*!', '', $before_ns_clean);
            $before_ns_clean = preg_replace('!#(?!\[)[^\n]*!', '', $before_ns_clean);

            if (preg_match('/^\s*use\s+[\w\\\\]+/m', $before_ns_clean)) {
                $errors[] = [
                    'type'    => 'UseBeforeNamespace',
                    'message' => 'use statement appears before namespace declaration. use must come AFTER namespace.',
                    'file'    => $rel_path,
                    'line'    => 1,
                ];
            }

            // Check for any executable statement before namespace (not just use).
            // Allow: <?php, declare(), comments, whitespace.
            $lines = explode("\n", $before_ns_clean);
            foreach ($lines as $idx => $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || $trimmed === '<?php' || $trimmed === '?>') {
                    continue;
                }
                if (preg_match('/^declare\s*\(/', $trimmed)) {
                    continue;
                }
                // If we hit something that's not a comment/declare/whitespace, it's suspicious.
                if ($trimmed !== '' && !preg_match('/^(\/\/|#|\/\*|\*|\*\/)/', $trimmed)) {
                    // Only flag if it looks like actual code (has a semicolon or paren).
                    if (preg_match('/[;{}()]/', $trimmed) && !preg_match('/^namespace\s/', $trimmed)) {
                        $errors[] = [
                            'type'    => 'StatementBeforeNamespace',
                            'message' => 'Executable statement before namespace declaration: "' . substr($trimmed, 0, 80) . '"',
                            'file'    => $rel_path,
                            'line'    => $idx + 1,
                        ];
                        break; // Report only the first one.
                    }
                }
            }
        }

        return $errors;
    }
}

/**
 * Compute relative path for display.
 */
if (!function_exists('linked3_ues_relpath')) {
    function linked3_ues_relpath($path, $base)
    {
        $base = rtrim($base, '/') . '/';
        if (strpos($path, $base) === 0) {
            return substr($path, strlen($base));
        }
        return $path;
    }
}

/**
 * Initialize and run the scanner. Called from linked3.php at the very top.
 */
if (!function_exists('linked3_ues_init')) {
    function linked3_ues_init($plugin_dir)
    {
        $errors = linked3_ues_scan($plugin_dir);

        if (!empty($errors)) {
            // Store for wp-early-error-handler and admin_notices to pick up.
            if (!isset($GLOBALS['linked3_early_errors']) || !is_array($GLOBALS['linked3_early_errors'])) {
                $GLOBALS['linked3_early_errors'] = [];
            }
            foreach ($errors as $err) {
                $GLOBALS['linked3_early_errors'][] = $err;
            }

            // If wp-early-error-handler is loaded, render via its batch page.
            if (function_exists('wp_eeh_render_batch_errors') && !defined('LINKED3_UES_RENDERED')) {
                define('LINKED3_UES_RENDERED', true);
                // Merge with any existing wp_eeh_errors.
                if (isset($GLOBALS['wp_eeh_errors']) && is_array($GLOBALS['wp_eeh_errors'])) {
                    $GLOBALS['wp_eeh_errors'] = array_merge($GLOBALS['wp_eeh_errors'], $errors);
                } else {
                    $GLOBALS['wp_eeh_errors'] = $errors;
                }
                $plugin_name = defined('WP_EEH_PLUGIN_NAME') ? WP_EEH_PLUGIN_NAME : 'Linked3 AI';
                wp_eeh_render_batch_errors($errors, $plugin_name);
                exit;
            }

            // Standalone renderer (if wp-early-error-handler is not loaded).
            if (!defined('LINKED3_UES_RENDERED')) {
                define('LINKED3_UES_RENDERED', true);
                linked3_ues_render_errors($errors);
                exit;
            }
        }
    }
}

/**
 * Standalone error renderer (fallback when wp-early-error-handler is not available).
 */
if (!function_exists('linked3_ues_render_errors')) {
    function linked3_ues_render_errors($errors)
    {
        $count = count($errors);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Linked3 AI — Ultra-Early Scanner: Batch Errors</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f0f0f1;color:#3c434a;margin:0;padding:40px 20px;}';
        echo '.wrap{max-width:1100px;margin:0 auto;}';
        echo '.header{background:#994900;color:#fff;padding:24px 30px;border-radius:4px 4px 0 0;}';
        echo '.header h1{margin:0;font-size:22px;font-weight:600;}';
        echo '.header .count{display:inline-block;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:13px;margin-left:10px;}';
        echo '.body{background:#fff;border:1px solid #dcdcde;border-top:none;padding:24px 30px;border-radius:0 0 4px 4px;}';
        echo '.intro{color:#646970;font-size:14px;margin:0 0 20px;line-height:1.6;}';
        echo '.error-card{background:#fff;border:1px solid #dcdcde;border-left:4px solid #994900;padding:16px 20px;margin-bottom:14px;border-radius:3px;}';
        echo '.error-card .num{display:inline-block;background:#994900;color:#fff;width:24px;height:24px;border-radius:50%;text-align:center;line-height:24px;font-size:12px;font-weight:600;margin-right:10px;}';
        echo '.error-card .type{display:inline-block;background:#fef7f0;color:#994900;padding:2px 8px;border-radius:3px;font-size:12px;font-family:monospace;margin-right:8px;}';
        echo '.error-card .msg{display:block;margin:8px 0 6px;font-family:monospace;font-size:13px;color:#1d2327;line-height:1.5;}';
        echo '.error-card .loc{font-family:monospace;font-size:12px;color:#646970;}';
        echo '.error-card .loc .file{color:#2271b1;}';
        echo '.footer{margin-top:24px;color:#646970;font-size:12px;text-align:center;}';
        echo '.footer code{background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:11px;}';
        echo '</style></head><body>';
        echo '<div class="wrap">';
        echo '<div class="header">';
        echo '<h1>Linked3 AI — Ultra-Early Scanner</h1>';
        echo '<span class="count">' . $count . ' error' . ($count > 1 ? 's' : '') . ' found</span>';
        echo '</div>';
        echo '<div class="body">';
        echo '<p class="intro">The ultra-early scanner ran <strong>before</strong> any business code was loaded. '
            . 'All issues found are listed below — fix them all, then reload.</p>';

        foreach ($errors as $i => $err) {
            $n = $i + 1;
            echo '<div class="error-card">';
            echo '<span class="num">' . $n . '</span>';
            echo '<span class="type">' . htmlspecialchars($err['type'] ?? 'Error') . '</span>';
            echo '<span class="msg">' . htmlspecialchars($err['message'] ?? '') . '</span>';
            echo '<span class="loc">File: <span class="file">' . htmlspecialchars($err['file'] ?? '') . '</span>:' . (int) ($err['line'] ?? 0) . '</span>';
            echo '</div>';
        }

        echo '<div class="footer">';
        echo 'Powered by <code>linked3-ultra-early-scanner.php v' . LINKED3_UES_VERSION . '</code>';
        echo '</div>';

        echo '</div></div>';
        echo '</body></html>';
    }
}
