<?php

$header = <<<EOF
This file is part of Sulu.

(c) Sulu GmbH

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
EOF;

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // Generated container/cache artifacts from booting the functional test kernel.
    ->exclude('Application/var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'header_comment' => ['header' => $header],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;
