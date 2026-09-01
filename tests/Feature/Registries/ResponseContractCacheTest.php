<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Lahatre\Shared\Http\Responses\ResponseMode;
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

it('applies the same default shape to catalog variant contracts', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $show = $registry->forRoute('lahatre.catalog.products.variants.show');
    $store = $registry->forRoute('lahatre.catalog.products.variants.store');
    $update = $registry->forRoute('lahatre.catalog.products.variants.update');

    expect($show)->not->toBeNull()
        ->and($store)->not->toBeNull()
        ->and($update)->not->toBeNull();

    $showShape = $show->shapes['default'];

    expect($showShape->requiredLoads)->toBe(['product', 'catalogItem', 'optionValues.option'])
        ->and(array_keys($showShape->includes))->toBe(['unit_group', 'units', 'labels', 'inventory'])
        ->and($store->shapes['default']->requiredLoads)->toBe($showShape->requiredLoads)
        ->and($store->shapes['default']->includes)->toEqual($showShape->includes)
        ->and($update->shapes['default']->includes)->toEqual($showShape->includes);
});

it('applies the same default shape to IAM auth and user contracts', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $login = $registry->forRoute('lahatre.iam.auth.login');
    $register = $registry->forRoute('lahatre.iam.auth.register');
    $me = $registry->forRoute('lahatre.iam.auth.me');
    $switchMemberRole = $registry->forRoute('lahatre.iam.auth.switch-member-role');

    expect($login)->not->toBeNull()
        ->and($register)->not->toBeNull()
        ->and($me)->not->toBeNull()
        ->and($switchMemberRole)->not->toBeNull()
        ->and($login->defaultMode)->toBe(ResponseMode::Resource)
        ->and($login->shapes['default']->requiredLoads)
        ->toBe(['organizationMemberships.memberRoles.role'])
        ->and($me->defaultMode)->toBe(ResponseMode::Resource)
        ->and($switchMemberRole->defaultMode)->toBe(ResponseMode::Resource)
        ->and($register->shapes['default']->requiredLoads)
        ->toBe($login->shapes['default']->requiredLoads)
        ->and($me->shapes['default']->requiredLoads)
        ->toBe($login->shapes['default']->requiredLoads)
        ->and($switchMemberRole->shapes['default']->requiredLoads)
        ->toBe($login->shapes['default']->requiredLoads);
});

it('maps every relation-backed resource operation to required loads or includes', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $expectations = [
        'lahatre.catalog.categories.show'        => ['bloodline' => ['bloodline']],
        'lahatre.catalog.options.show'           => ['values' => ['values']],
        'lahatre.catalog.options.values.show'    => ['option' => ['option']],
        'lahatre.catalog.products.variants.show' => ['unit_group' => ['catalogItem.unitGroup']],
        'lahatre.inventory.movements.index'      => ['location' => ['location']],
        'lahatre.inventory.transactions.show'    => ['movements' => ['movements']],
        'lahatre.inventory.stocks.update'        => ['unit' => ['unit']],
        'lahatre.master.units.index'             => ['group' => ['group']],
        'lahatre.master.units.upsert'            => ['units' => ['units']],
    ];

    foreach ($expectations as $routeName => $includes) {
        $contract = $registry->forRoute($routeName);
        $shape = $contract?->resolveShape(null);

        expect($shape)->not->toBeNull();

        foreach ($includes as $include => $loads) {
            expect($shape->relationsToLoad([$include]))->toContain(...$loads);
        }
    }

    expect($registry->forRoute('lahatre.iam.auth.login')?->resolveShape(null)->requiredLoads)
        ->toContain('organizationMemberships.memberRoles.role');
});

it('does not expose variant labels as a product-level include', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->discover();

    $productShape = $registry
        ->forRoute('lahatre.catalog.products.show')
        ?->resolveShape(null);

    expect($productShape)->not->toBeNull()
        ->and($productShape->includes)->not->toHaveKey('labels');
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

    expect($cachedDefinitions)->toHaveKey('lahatre.catalog.products.variants.show')
        ->and(array_key_exists('_shapes', $cachedDefinitions))->toBeFalse()
        ->and($cachedDefinitions['lahatre.catalog.products.variants.show']['shapes']['default'])
        ->and(array_key_exists('ref', $cachedDefinitions['lahatre.catalog.products.variants.show']['shapes']['default']))->toBeFalse()
        ->and($cachedDefinitions['lahatre.inventory.stocks.summary.index'])
        ->toEqual([])
        ->and($cachedDefinitions['lahatre.inventory.items.locations.lots.index'])
        ->toEqual([])
        ->and($cachedDefinitions['lahatre.inventory.stocks.update']['shapes']['projection'])
        ->not->toHaveKey('ref');
});

it('clears the generated response contract cache', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->cache();
    $registry->clear();

    expect(File::exists(responseContractCachePath()))->toBeFalse();
});
