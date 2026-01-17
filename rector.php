<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        // __DIR__.'/packages',
        __DIR__.'/config',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/bootstrap',
        __DIR__.'/storage',
        __DIR__.'/vendor',
        __DIR__.'/node_modules',
        // Skip redondante instanceof checks pour les types de retour stricts
        ExplicitBoolCompareRector::class,
        // Skip instanceof pour les fonctions avec type de retour strict (?Type)
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
    ->withPhpSets(
        php84: true,
    )
    ->withPreparedSets(
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSets([
        SetList::TYPE_DECLARATION,
        LaravelSetList::LARAVEL_120,
        // LaravelSetList::LARAVEL_IF_HELPERS,
    ])
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: false,
        removeUnusedImports: true
    );
