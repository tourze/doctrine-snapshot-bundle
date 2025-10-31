<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('tests/var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP82Migration' => true,
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        '@PHPUnit100Migration:risky' => true,
        '@Symfony' => true,
        'concat_space' => ['spacing' => 'one'],
        'phpdoc_summary' => false,
        'fully_qualified_strict_types' => [
            'import_symbols' => true, // 自动添加 use 语句
        ],
        'class_attributes_separation' => [
            'elements' => [
                'property' => 'one',
                'method' => 'one',
                'trait_import' => 'none',
            ],
        ],
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],
        'phpdoc_no_alias_tag' => false,
        'phpdoc_types' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_var_without_name' => false,
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_separation' => false,
        'phpdoc_align' => false,

        // 因为AI习惯先import再继续编辑其他文件，这里不放行的话，会出现重复修改文件的情况
        'no_unused_imports' => false,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setIndent('    ')
    ->setLineEnding("\n")
;