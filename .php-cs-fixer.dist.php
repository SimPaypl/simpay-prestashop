<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__,
    ])
    ->exclude([
        'vendor',
        'ps_root_dir',
    ]);

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'concat_space' => ['spacing' => 'one'],
        'protected_to_private' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'final_class' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);