<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app')
;

return PhpCsFixer\Config::create()
    ->setRules(array(
        '@PSR2' => true,
        'single_import_per_statement' => false
    ))
    ->setFinder($finder)
;