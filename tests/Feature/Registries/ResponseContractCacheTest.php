<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Lahatre\Shared\Registries\ResponseContractRegistry;

function responseContractCachePath(): string
{
    return App::bootstrapPath('cache/response-contracts.php');
}

beforeEach(function (): void {
    config(['app.env' => 'testing']);

    if (File::exists(responseContractCachePath())) {
        File::delete(responseContractCachePath());
    }
});

afterEach(function (): void {
    config(['app.env' => 'testing']);

    if (File::exists(responseContractCachePath())) {
        File::delete(responseContractCachePath());
    }
});

it('discovers response contracts from the application and module config files', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    expect($registry->forRoute('lahatre.catalog.products.variants.show'))->not->toBeNull();
});

it('resolves reusable shapes for catalog variant contracts', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $show = $registry->forRoute('lahatre.catalog.products.variants.show');
    $store = $registry->forRoute('lahatre.catalog.products.variants.store');
    $update = $registry->forRoute('lahatre.catalog.products.variants.update');

    expect($show)->not->toBeNull()
        ->and($store)->not->toBeNull()
        ->and($update)->not->toBeNull();

    $showShape = $show->shapes['detail'];

    expect($showShape->requiredLoads)->toBe(['product', 'optionValues.option', 'unitGroup'])
        ->and(array_keys($showShape->includes))->toBe(['tags', 'inventory'])
        ->and($store->shapes['detail']->requiredLoads)->toBe($showShape->requiredLoads)
        ->and($update->shapes['detail']->includes)->toEqual($showShape->includes);
});

it('ignores a response contract cache outside production and discovers definitions', function (): void {
    config(['app.env' => 'local']);

    $cachePath = responseContractCachePath();
    $customDefinitions = ['custom.route' => []];

    File::put($cachePath, '<?php return '.var_export($customDefinitions, true).';');

    $registry = new ResponseContractRegistry;
    $registry->discover();

    expect($registry->forRoute('lahatre.catalog.products.variants.show'))->not->toBeNull()
        ->and($registry->forRoute('custom.route'))->toBeNull();
});

it('loads response contracts from the generated cache in production', function (): void {
    config(['app.env' => 'production']);

    $cachePath = responseContractCachePath();
    $customDefinitions = ['custom.route' => []];

    File::put($cachePath, '<?php return '.var_export($customDefinitions, true).';');

    $cachedRegistry = new ResponseContractRegistry;
    $cachedRegistry->discover();

    expect($cachedRegistry->forRoute('custom.route'))->not->toBeNull()
        ->and($cachedRegistry->forRoute('lahatre.catalog.products.variants.show'))->toBeNull();
});

it('caches response contracts from discovered definitions when no cache exists', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->cache();

    expect(File::exists(responseContractCachePath()))->toBeTrue();

    $cachedDefinitions = require responseContractCachePath();

    expect($cachedDefinitions)
        ->toHaveKey('lahatre.catalog.products.variants.show')
        ->not->toHaveKey('_shapes')
        ->and($cachedDefinitions['lahatre.catalog.products.variants.show']['shapes']['detail'])
        ->not->toHaveKey('ref');
});

it('clears the generated response contract cache', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->cache();
    $registry->clear();

    expect(File::exists(responseContractCachePath()))->toBeFalse();
});
