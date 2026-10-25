<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationAnalyzer;
use Lahatre\ResponseSelection\Exceptions\ResponseDeclarationException;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\ResourceDefinition;

final class ResourceDefinitionTestChildResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id');
    }
}

class ResourceDefinitionTestParentResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->include('cashiers', ResourceDefinitionTestChildResource::class);
    }
}

final class ResourceDefinitionTestExtendingResource extends ResourceDefinitionTestParentResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        $resource->field('balance', columns: ['ledger_account_id']);
    }
}

final class ResourceDefinitionTestRedeclaringResource extends ResourceDefinitionTestParentResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        $resource
            ->field('balance', columns: ['ledger_account_id'])
            ->include('cashiers', ResourceDefinitionTestChildResource::class);
    }
}

final class ResourceDefinitionTestNotAComputer {}

final class ResourceDefinitionTestWrongComputerResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('balance', computedBy: ResourceDefinitionTestNotAComputer::class);
    }
}

it('loads the camelCase relation of an include by default', function (): void {
    $definition = (new ResourceDefinition)->include('source_location', ResourceDefinitionTestChildResource::class);

    expect($definition->includeRelation('source_location'))->toBe('sourceLocation')
        ->and($definition->includeForRelation('sourceLocation'))->toBe('source_location');
});

it('loads the relation an include names', function (): void {
    $definition = (new ResourceDefinition)->include('roles', ResourceDefinitionTestChildResource::class, relation: 'groupedRoles');

    expect($definition->includeRelation('roles'))->toBe('groupedRoles');
});

it('loads no relation for a virtual include', function (): void {
    $definition = (new ResourceDefinition)
        ->virtualInclude('source_location', ResourceDefinitionTestChildResource::class, fn () => null)
        ->include('location', ResourceDefinitionTestChildResource::class, relation: 'sourceLocation');

    expect($definition->includeRelation('source_location'))->toBeNull()
        ->and($definition->isVirtualInclude('source_location'))->toBeTrue();
});

it('rejects a name that is not a snake_case JSON key', function (string $name): void {
    (new ResourceDefinition)->include($name, ResourceDefinitionTestChildResource::class);
})->with(['sourceLocation', 'Source_location', '_source', 'source__location', 'source_'])
    ->throws(ResponseSelectionException::class);

it('rejects two includes loading the same relation', function (): void {
    (new ResourceDefinition)
        ->include('levels', ResourceDefinitionTestChildResource::class, relation: 'inventoryLevels')
        ->include('inventory_levels', ResourceDefinitionTestChildResource::class);
})->throws(ResponseSelectionException::class);

it('reads the column of its key when declared bare', function (): void {
    $definition = (new ResourceDefinition)->field('email');

    expect($definition->fieldDefinition('email'))->toBe([
        'columns'    => ['email'],
        'relations'  => [],
        'fields'     => [],
        'computedBy' => null,
        'when'       => null,
        'default'    => true,
    ]);
});

it('reads only what a field declares once it declares a dependency', function (): void {
    $definition = (new ResourceDefinition)
        ->field('options', relations: ['optionValues.option'])
        ->field('parent', columns: [])
        ->field('status', columns: ['status', 'expired_at'])
        ->field('label', fields: ['status']);

    expect($definition->fieldDefinition('options')['columns'] ?? null)->toBe([])
        ->and($definition->fieldDefinition('parent')['columns'] ?? null)->toBe([])
        ->and($definition->fieldDefinition('status')['columns'] ?? null)->toBe(['status', 'expired_at'])
        ->and($definition->fieldDefinition('label')['columns'] ?? null)->toBe([]);
});

it('rejects a computedBy class that is not a FieldComputer', function (): void {
    (new ResponseDeclarationAnalyzer)->validateResource(ResourceDefinitionTestWrongComputerResource::class);
})->throws(ResponseDeclarationException::class);

it('rejects a member declared twice at freeze and names the resource', function (string $first, string $second): void {
    $definition = new ResourceDefinition;

    foreach ([$first, $second] as $kind) {
        $kind === 'field'
            ? $definition->field('status')
            : $definition->include('status', ResourceDefinitionTestChildResource::class);
    }

    $definition->freeze(ResourceDefinitionTestChildResource::class);
})->throws(ResponseSelectionException::class, ResourceDefinitionTestChildResource::class.' declares [status] twice')->with([
    'field twice'        => ['field', 'field'],
    'field then include' => ['field', 'include'],
    'include then field' => ['include', 'field'],
    'include twice'      => ['include', 'include'],
]);

it('rejects a child Resource redeclaring a member of parent::define()', function (): void {
    ResourceDefinitionTestRedeclaringResource::definition();
})->throws(ResponseSelectionException::class, ResourceDefinitionTestRedeclaringResource::class.' declares [cashiers] twice');

it('lets a child Resource add members after those of parent::define()', function (): void {
    expect(ResourceDefinitionTestExtendingResource::definition()->members())->toBe(['id', 'cashiers', 'balance']);
});

it('gives an include the configured default, or the limit it sets up to the configured maximum', function (): void {
    config(['response-selection.limits.default_include_items' => 30, 'response-selection.limits.max_include_items' => 60]);

    $definition = (new ResourceDefinition)
        ->include('cashiers', ResourceDefinitionTestChildResource::class)
        ->include('roles', ResourceDefinitionTestChildResource::class, limit: 5)
        ->include('permissions', ResourceDefinitionTestChildResource::class, limit: 60)
        ->virtualInclude('peers', ResourceDefinitionTestChildResource::class, fn () => [], asList: true, limit: 45)
        ->freeze(ResourceDefinitionTestChildResource::class);

    expect($definition->includeLimit('cashiers'))->toBe(30)
        ->and($definition->includeLimit('roles'))->toBe(5)
        ->and($definition->includeLimit('permissions'))->toBe(60)
        ->and($definition->includeLimit('peers'))->toBe(45);
});

it('rejects an include limit above the configured maximum or below 1 at freeze', function (int $limit): void {
    config(['response-selection.limits.default_include_items' => 30, 'response-selection.limits.max_include_items' => 60]);

    (new ResourceDefinition)
        ->include('cashiers', ResourceDefinitionTestChildResource::class, limit: $limit)
        ->freeze(ResourceDefinitionTestChildResource::class);
})->throws(ResponseSelectionException::class, 'include [cashiers] has limit')->with([61, 0]);

it('rejects a configured default above the configured maximum at freeze', function (): void {
    config(['response-selection.limits.default_include_items' => 70, 'response-selection.limits.max_include_items' => 60]);

    (new ResourceDefinition)
        ->include('cashiers', ResourceDefinitionTestChildResource::class)
        ->freeze(ResourceDefinitionTestChildResource::class);
})->throws(ResponseSelectionException::class, 'include [cashiers] has limit 70');
