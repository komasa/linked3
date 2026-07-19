<?php
/**
 * PHP CS Fixer 配置 — Linked3 AI 商业生产级标准
 * 
 * 基于 PSR-12 + 额外严格规则
 * 使用: vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
 */

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/lib')
    ->in(__DIR__ . '/admin')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->exclude('assets')
    ->name('*.php')
    ->notPath('tests/')  // 测试文件单独配置
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // ── PSR 标准 ──────────────────────────────────
        '@PSR1' => true,
        '@PSR2' => true,
        '@PSR12' => true,
        
        // ── 严格类型 ──────────────────────────────────
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        
        // ── 命名规范 ──────────────────────────────────
        'no_unneeded_import_alias' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'native_function_invocation' => ['include' => ['@all']],
        
        // ── 数组规范 ──────────────────────────────────
        'array_syntax' => ['syntax' => 'short'],
        'trim_array_spaces' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        'normalize_index_brace' => true,
        
        // ── 运算符规范 ─────────────────────────────────
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal'],
        ],
        'unary_operator_spaces' => true,
        'not_operator_with_successor_space' => true,
        
        // ── 控制结构 ──────────────────────────────────
        'elseif' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'simplified_if_return' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        
        // ── 函数规范 ──────────────────────────────────
        'function_typehint_space' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'method_visibility_required' => true,
        'visibility_required' => true,
        
        // ── 类规范 ────────────────────────────────────
        'class_attributes_separation' => [
            'elements' => ['method' => 'one', 'property' => 'one', 'const' => 'one']
        ],
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'single_class_element_per_statement' => true,
        'single_trait_insert_per_statement' => true,
        
        // ── PHPDoc ────────────────────────────────────
        'phpdoc_align' => ['align' => 'vertical'],
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_package' => true,
        'phpdoc_order' => ['order' => ['param', 'return', 'throws']],
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => true,
        'phpdoc_to_comment' => ['ignored_tags' => ['var', 'todo']],
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,
        
        // ── 现代PHP特性 ───────────────────────────────
        'short_list_syntax' => true,
        'list_syntax' => ['syntax' => 'short'],
        'normalize_index_brace' => true,
        'dir_constant' => true,
        'modernize_types_casting' => true,
        'no_alias_functions' => true,
        'no_homoglyph_names' => true,
        'no_php4_constructor' => true,
        
        // ── 清理 ──────────────────────────────────────
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
                'use_trait',
                'case',
                'default',
                'switch',
                'curly_brace_block',
                'parenthesis_brace_block',
                'square_brace_block',
            ]
        ],
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => ['use' => 'echo'],
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_short_bool_cast' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_spaces_around_offset' => true,
        'no_trailing_comma_in_list_call' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_curly_braces' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        
        // ── 安全相关 ──────────────────────────────────
        'no_eval' => true,
        'no_alias_language_construct_call' => true,
    ])
    ->setFinder($finder)
    ->setLineEnding("\n");
