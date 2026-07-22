<?php
/**
 * Linked3 AI — Symbol Analyzer (extracted from early-error-handler)
 * 
 * PHP tokenizer-based symbol extraction and reference resolution.
 * Loaded by linked3-early-error-handler.php via require_once.
 *
 * @package Linked3
 */

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

if (!function_exists('linked3_early_handler_parse_namespace')) {
    /**
     * Parse a namespace declaration from tokens starting at position $i.
     * Returns [namespace_string, new_position].
     */
    function linked3_early_handler_parse_namespace(array $tokens, int $i, int $n): array
    {
        $name_parts = [];
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
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
        $namespace = !empty($name_parts) ? str_replace('\\\\', '\\', implode('', $name_parts)) : null;
        return [$namespace, $j + 1];
    }
}

if (!function_exists('linked3_early_handler_parse_use_statement')) {
    /**
     * Parse a namespace-level use statement from tokens at position $i.
     * Returns [ns_uses_array, new_position].
     */
    function linked3_early_handler_parse_use_statement(array $tokens, int $i, int $n): array
    {
        $ns_uses = [];
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
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
                $fqcn = ltrim(str_replace('\\\\', '\\', $group_prefix . $item), '\\');
                $alias = substr(strrchr($fqcn, '\\'), 1) ?: $fqcn;
                $ns_uses[$alias] = $fqcn;
            }
        } else {
            $fqcn = ltrim(str_replace('\\\\', '\\', implode('', $target_parts)), '\\');
            $alias = substr(strrchr($fqcn, '\\'), 1) ?: $fqcn;
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
        return [$ns_uses, $j + 1];
    }
}

if (!function_exists('linked3_early_handler_parse_class_decl')) {
    /**
     * Parse a class/trait/interface declaration and its extends/implements.
     * Returns [declaration_array, references_array, current_class, new_position].
     */
    function linked3_early_handler_parse_class_decl(array $tokens, int $i, int $n, string $namespace, int $line): array
    {
        $id = $tokens[$i][0];
        $kind_map = [T_CLASS => 'class', T_INTERFACE => 'interface', T_TRAIT => 'trait'];
        $kind = $kind_map[$id];
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        $declaration = null;
        $references = [];
        $current_class = null;
        if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            $name = $tokens[$j][1];
            $fqcn = $namespace ? $namespace . '\\' . $name : $name;
            $declaration = ['kind' => $kind, 'name' => $fqcn, 'line' => $line];
            $current_class = $fqcn;
            $k = $j + 1;
            while ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                $k++;
            }
            // extends
            if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_EXTENDS) {
                $k++;
                $ext_parts = [];
                while ($k < $n) {
                    $t = $tokens[$k];
                    if ($t === '{' || $t === ';') break;
                    if (is_array($t) && $t[0] === T_IMPLEMENTS) break;
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $ext_parts[] = $t[1];
                    }
                    $k++;
                }
                if (!empty($ext_parts)) {
                    $ext = str_replace('\\\\', '\\', implode('', $ext_parts));
                    $references[] = ['kind' => 'extends', 'symbol' => $ext, 'line' => $line, 'context' => "$kind $name extends"];
                }
            }
            // implements
            if ($k < $n && is_array($tokens[$k]) && $tokens[$k][0] === T_IMPLEMENTS) {
                $k++;
                $impl_parts = [];
                while ($k < $n) {
                    $t = $tokens[$k];
                    if ($t === '{') break;
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $impl_parts[] = $t[1];
                    } elseif ($t === ',') {
                        $impl_parts[] = ',';
                    }
                    $k++;
                }
                if (!empty($impl_parts)) {
                    $impl_str = str_replace('\\\\', '\\', implode('', $impl_parts));
                    foreach (explode(',', $impl_str) as $impl) {
                        $impl = trim($impl);
                        if ($impl !== '') {
                            $references[] = ['kind' => 'implements', 'symbol' => $impl, 'line' => $line, 'context' => "$kind $name implements"];
                        }
                    }
                }
            }
        }
        return [$declaration, $references, $current_class, $j + 1];
    }
}


if (!function_exists('linked3_early_handler_extract_symbol_info')) {
    /**
     * Extract namespace, class/trait/interface declarations, use statements,
     * extends/implements clauses from a PHP file using the tokenizer.
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
        $ns_uses = [];
        $body_depth = 0;
        $current_class = null;

        $i = 0;
        $n = count($tokens);
        while ($i < $n) {
            $tok = $tokens[$i];
            if (!is_array($tok)) {
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
            $line = $tok[2];

            // namespace declaration
            if ($id === T_NAMESPACE) {
                [$namespace, $i] = linked3_early_handler_parse_namespace($tokens, $i, $n);
                continue;
            }

            // namespace-level use statement
            if ($id === T_USE && $body_depth === 0) {
                [$new_uses, $i] = linked3_early_handler_parse_use_statement($tokens, $i, $n);
                $ns_uses = array_merge($ns_uses, $new_uses);
                continue;
            }

            // class-level use (trait usage)
            if ($id === T_USE && $body_depth > 0 && $current_class !== null) {
                $j = $i + 1;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                $name_parts = [];
                while ($j < $n) {
                    $t = $tokens[$j];
                    if ($t === ';' || $t === ',' || $t === '{') break;
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $name_parts[] = $t[1];
                    }
                    $j++;
                }
                $name = str_replace('\\\\', '\\', implode('', $name_parts));
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
                [$decl, $refs, $current_class, $i] = linked3_early_handler_parse_class_decl($tokens, $i, $n, $namespace, $line);
                if ($decl !== null) {
                    $declarations[] = $decl;
                }
                $references = array_merge($references, $refs);
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


if (!function_exists('linked3_early_handler_collect_symbols')) {
    /**
     * Pass 1: Iterate plugin files, collect declarations and references.
     * Returns [symtab, all_refs, files_list].
     */
    function linked3_early_handler_collect_symbols($iterator, $skip_paths, $plugin_dir): array
    {
        $symtab = [];
        $all_refs = [];
        $files = [];

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

            $files[] = $path;
            $rel = linked3_early_handler_relpath($path, $plugin_dir);
            $info = linked3_early_handler_extract_symbol_info($source);
            $namespace = $info['namespace'];
            $ns_uses = isset($info['ns_uses']) ? $info['ns_uses'] : [];

            foreach ($info['declarations'] as $decl) {
                $symtab[$decl['name']] = [
                    'file' => $rel,
                    'line' => $decl['line'],
                    'kind' => $decl['kind'],
                ];
            }

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

        return [$symtab, $all_refs, $files];
    }
}

if (!function_exists('linked3_early_handler_resolve_missing_refs')) {
    /**
     * Pass 2: Check references against symbol table + PSR-4 autoload.
     * Returns array of MissingSymbol errors.
     */
    function linked3_early_handler_resolve_missing_refs(array $all_refs, array $symtab, string $plugin_dir): array
    {
        $errors = [];
        foreach ($all_refs as $ref) {
            $fqcn = $ref['fqcn'];
            if (strpos($fqcn, 'Linked3') !== 0) {
                continue;
            }
            if (isset($symtab[$fqcn])) {
                continue;
            }

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

            $errors[] = [
                'type'    => 'MissingSymbol',
                'message' => ucfirst($ref['kind']) . ' references "' . $fqcn . '" which is not defined anywhere in the plugin. ' .
                             'Context: ' . $ref['context'] . '. ' .
                             'Likely cause: missing `use` import or wrong namespace.',
                'file'    => $ref['file'],
                'line'    => $ref['line'],
            ];
        }
        return $errors;
    }
}

if (!function_exists('linked3_early_handler_check_interface_methods')) {
    /**
     * G3.4: Detect classes that implement interfaces but don't implement all methods.
     */
    function linked3_early_handler_check_interface_methods(array $files): array
    {
        $errors = [];
        foreach ($files as $file_path) {
            $code_content = @file_get_contents($file_path);
            if ($code_content === false) continue;

            if (preg_match_all('/class\s+(\w+).*?implements\s+([\w\\\s,]+)/', $code_content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $cls_name = $m[1];
                    $ifaces = array_map('trim', explode(',', $m[2]));
                    foreach ($ifaces as $iface) {
                        $iface = ltrim($iface, '\\');
                        $iface_short = substr(strrchr($iface, '\\'), 1) ?: $iface;
                        $iface_lower = strtolower(str_replace('_', '-', $iface_short));

                        $iface_file = null;
                        $possible_paths = [
                            dirname($file_path) . "/interface-{$iface_lower}.php",
                            dirname($file_path) . "/class-{$iface_lower}.php",
                        ];
                        foreach ($possible_paths as $p) {
                            if (file_exists($p)) { $iface_file = $p; break; }
                        }

                        if ($iface_file) {
                            $iface_code = @file_get_contents($iface_file);
                            if ($iface_code === false) continue;
                            if (preg_match_all('/public\s+function\s+(\w+)\s*\([^)]*\)\s*;/i', $iface_code, $iface_methods)) {
                                foreach ($iface_methods[1] as $method_name) {
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
        return $errors;
    }
}

if (!function_exists('linked3_early_handler_check_bare_class_refs')) {
    /**
     * G3.4: Detect bare class names in add_action at file scope.
     */
    function linked3_early_handler_check_bare_class_refs(array $files): array
    {
        $errors = [];
        foreach ($files as $file_path) {
            $code_content = @file_get_contents($file_path);
            if ($code_content === false) continue;

            $lines = explode("\n", $code_content);
            $brace_depth = 0;
            foreach ($lines as $i => $line) {
                $brace_depth += substr_count($line, '{') - substr_count($line, '}');
                if (preg_match('/add_(action|filter)\s*\(.*\[\s*[\'"]Linked3_/', $line)
                    && !preg_match('/\\\\Linked3/', $line)
                    && $brace_depth <= 0) {
                    $errors[] = [
                        'type'    => 'BareClassRef',
                        'message' => 'add_action/add_filter uses bare class name at file scope. The autoloader only handles namespaced (Linked3\\*) symbols. Use FQCN: [\'\\\\Namespace\\\\SymbolClassName\', \'method\'].',
                        'file'    => $file_path,
                        'line'    => $i + 1,
                    ];
                }
            }
        }
        return $errors;
    }
}

if (!function_exists('linked3_early_handler_check_filescope_magic_const')) {
    /**
     * G3.4: Detect file-scope __CLASS__ in add_action/add_filter.
     */
    function linked3_early_handler_check_filescope_magic_const(array $files): array
    {
        $errors = [];
        foreach ($files as $file_path) {
            $code = @file_get_contents($file_path);
            if ($code === false) continue;

            $lines = explode("\n", $code);
            $brace_depth = 0;
            foreach ($lines as $i => $line) {
                $brace_depth += substr_count($line, '{') - substr_count($line, '}');
                if (preg_match('/add_(action|filter)\s*\(.*\[\s*[\'"]__CLASS__[\'"]\s*,/', $line)) {
                    if ($brace_depth <= 0) {
                        $errors[] = [
                            'type'    => 'FileScopeMagicConst',
                            'message' => 'add_action/add_filter uses __CLASS__ at file scope (outside class body). __CLASS__ resolves to empty string, causing Fatal Error. Use the FQCN string instead, e.g. [\'\\\\Linked3\\\\NS\\\\ClassName\', \'method\'].',
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

if (!function_exists('linked3_early_handler_symbol_scan')) {
    /**
     * Scan all PHP files for missing class/trait/interface references.
     * Catches "Trait not found" / "Class not found" errors that
     * the syntax scan cannot detect (these are RUNTIME, not PARSE errors).
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
        [$symtab, $all_refs, $files] = linked3_early_handler_collect_symbols($iterator, $skip_paths, $plugin_dir);

        // Pass 2: resolve references against symbol table + PSR-4 autoload
        $errors = array_merge($errors, linked3_early_handler_resolve_missing_refs($all_refs, $symtab, $plugin_dir));

        // G3.4: Interface method implementation check
        $errors = array_merge($errors, linked3_early_handler_check_interface_methods($files));

        // G3.4: Bare class name detection
        $errors = array_merge($errors, linked3_early_handler_check_bare_class_refs($files));

        // G3.4: File-scope __CLASS__ detection
        $errors = array_merge($errors, linked3_early_handler_check_filescope_magic_const($files));

        return $errors;
    }
}