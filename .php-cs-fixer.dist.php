<?php

$header = <<<'EOF'
This file is part of Hyperf.

@link     https://www.hyperf.io
@document https://hyperf.wiki
@contact  group@hyperf.io
@license  https://github.com/hyperf/hyperf/blob/master/LICENSE
EOF;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        '@PhpCsFixer' => true,
        'header_comment' => ['header' => $header, 'separate' => 'none'],
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'blank_line_before_statement' => ['statements' => ['declare']],
        'general_phpdoc_annotation_remove' => [
            'annotations' => [
                'author',
                'package',
            ],
        ],
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'phpdoc_types_order' => ['null_adjustment' => 'always_first', 'sort_algorithm' => 'none'],
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'single_line_throw' => false,
        'increment_style' => ['style' => 'post'],
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'single_class_element_per_statement' => ['elements' => ['property']],
        'function_declaration' => [
            'closure_fn_spacing' => 'none',
        ],
        'strict_param' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('vendor')
            ->exclude('runtime')
            ->exclude('migrations')
            ->in(__DIR__)
    );
