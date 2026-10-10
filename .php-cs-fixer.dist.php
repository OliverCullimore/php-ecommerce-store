<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/migrations', __DIR__ . '/bin'])->exclude('shim');

return (new PhpCsFixer\Config())
    ->setRules(['@PSR12' => true, 'declare_strict_types' => true, 'ordered_imports' => true, 'no_unused_imports' => true])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
