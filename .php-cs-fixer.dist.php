<?php

declare(strict_types=1);

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS'                                       => true,
        '@PSR12'                                        => true,
        '@PHP82Migration'                               => true,
        'array_syntax'                                  => ['syntax' => 'short'],
        'php_unit_internal_class'                       => ['types' => ['normal', 'final']],
        'php_unit_namespaced'                           => true,
        'php_unit_expectation'                          => true,
        'php_unit_strict'                               => ['assertions' => ['assertAttributeEquals', 'assertAttributeNotEquals', 'assertEquals', 'assertNotEquals']],
        'php_unit_set_up_tear_down_visibility'          => true,
        'phpdoc_align'                                  => true,
        'phpdoc_indent'                                 => true,
        'phpdoc_inline_tag_normalizer'                  => true,
        'phpdoc_no_access'                              => true,
        'phpdoc_no_alias_tag'                           => true,
        'phpdoc_no_empty_return'                        => true,
        'phpdoc_no_package'                             => true,
        'phpdoc_param_order'                            => true,
        'phpdoc_return_self_reference'                  => true,
        'phpdoc_scalar'                                 => true,
        'phpdoc_separation'                             => true,
        'phpdoc_single_line_var_spacing'                => true,
        'phpdoc_summary'                                => true,
        'phpdoc_tag_casing'                             => true,
        'phpdoc_tag_type'                               => true,
        'phpdoc_to_comment'                             => false,
        'phpdoc_trim'                                   => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_var_without_name'                       => true,
        'phpdoc_no_useless_inheritdoc'                  => true,
        'align_multiline_comment'                       => true,
        'phpdoc_add_missing_param_annotation'           => ['only_untyped' => true],
        'binary_operator_spaces'                        => [
            'operators' => [
                '*=' => 'align_single_space_minimal',
                '+=' => 'align_single_space_minimal',
                '-=' => 'align_single_space_minimal',
                '/=' => 'align_single_space_minimal',
                '='  => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'heredoc_to_nowdoc'       => true,
        'ordered_imports'         => ['imports_order' => ['class', 'function', 'const',]],
        'declare_equal_normalize' => ['space' => 'none'],
        'declare_parentheses'     => true,
        'declare_strict_types'    => true,
        //'global_namespace_import' => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
    ])
    ->setLineEnding("\n")
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests')
    )
;

return $config;
