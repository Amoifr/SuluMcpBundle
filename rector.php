<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // Generated container/cache artifacts from booting the functional test kernel.
        __DIR__ . '/tests/Application/var',
    ])
    ->withImportNames(importShortClasses: false)
    ->withPreparedSets(codeQuality: true)
    ->withPhpSets(php82: true);
