<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // Equivalent to LevelSetList::UP_TO_PHP_84
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        codeQuality: true,
        deadCode: true,
    );
