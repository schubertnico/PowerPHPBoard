<?php

declare(strict_types=1);

/*
 * PHP-CS-Fixer Konfiguration fuer PowerPHPBoard
 *
 * Basiert auf PSR-12 mit zusaetzlichen Regeln fuer Konsistenz.
 *
 * Verwendung:
 *   composer cs-check   # Prueft Code-Style (keine Aenderungen)
 *   composer cs-fix     # Behebt Code-Style Probleme
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'vendor',
        '.docker',
        'logs',
        'tools',
        'node_modules',
    ])
    ->notPath([
        'config.inc.php',  // Konfigurationsdatei ausschliessen
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // PSR-12 als Basis
        '@PSR12' => true,

        // PHP-Version Features
        '@PHP84Migration' => true,

        // Array-Syntax
        'array_syntax' => ['syntax' => 'short'],
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        'trim_array_spaces' => true,
        'normalize_index_brace' => true,

        // Klammern und Leerzeichen
        'no_spaces_around_offset' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
            ],
        ],
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,

        // Imports/Use Statements
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'no_unused_imports' => true,
        'single_import_per_statement' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Klassen und Methoden
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
            ],
        ],
        'visibility_required' => [
            'elements' => ['property', 'method', 'const'],
        ],
        'single_class_element_per_statement' => true,

        // Operatoren
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=>' => 'single_space',
                '=' => 'single_space',
            ],
        ],
        'concat_space' => ['spacing' => 'one'],
        'unary_operator_spaces' => true,
        'not_operator_with_successor_space' => false,
        'object_operator_without_whitespace' => true,

        // Kontrollstrukturen
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_braces' => true,
        'control_structure_braces' => true,
        'control_structure_continuation_position' => [
            'position' => 'same_line',
        ],
        'elseif' => true,
        'no_break_comment' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,

        // Funktionen
        'function_declaration' => [
            'closure_function_spacing' => 'one',
            'closure_fn_spacing' => 'one',
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'return_type_declaration' => [
            'space_before' => 'none',
        ],
        'nullable_type_declaration_for_default_null_value' => true,

        // Strings
        'single_quote' => ['strings_containing_single_quote_chars' => false],
        'no_binary_string' => true,

        // Kommentare
        'single_line_comment_style' => ['comment_types' => ['hash']],
        'no_empty_comment' => true,
        'multiline_comment_opening_closing' => true,

        // PHPDoc
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_indent' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'remove_inheritdoc' => false,
        ],

        // Return/Yield
        'no_useless_return' => true,
        'simplified_null_return' => true,

        // Semicolons
        'no_empty_statement' => true,
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'no_singleline_whitespace_before_semicolons' => true,

        // Cast
        'cast_spaces' => ['space' => 'single'],
        'lowercase_cast' => true,
        'short_scalar_cast' => true,
        'no_short_bool_cast' => true,

        // Allgemein
        'encoding' => true,
        'full_opening_tag' => true,
        'no_closing_tag' => true,
        'line_ending' => true,
        'single_blank_line_at_eof' => true,
        'blank_line_after_opening_tag' => true,
        'declare_strict_types' => false,  // Nicht automatisch hinzufuegen
        'strict_param' => false,  // Nicht automatisch aendern

        // Typ-System
        'type_declaration_spaces' => true,

        // Yoda Style deaktivieren (natuerliche Reihenfolge)
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
