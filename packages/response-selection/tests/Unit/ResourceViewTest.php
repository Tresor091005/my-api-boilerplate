<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationAnalyzer;
use Lahatre\ResponseSelection\Exceptions\ResponseDeclarationException;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\ResourceResolver;
use Lahatre\ResponseSelection\Selection\SelectionParser;
use Lahatre\ResponseSelection\Selection\SelectionValidator;
use Lahatre\ResponseSelection\Selection\SelectionViews;
use Lahatre\ResponseSelection\Testing\RendersApiResources;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleDepotResource;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleTransferLineResource;

uses(RendersApiResources::class);

final class ResourceViewTestModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

final class ResourceViewTestLeafResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id')->field('name')->view('reference', fields: 'id,name');
    }
}

final class ResourceViewTestUnknownChildFieldResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->include('lines', ExampleTransferLineResource::class)
            ->view('broken', fields: 'id', include: 'lines(colour)');
    }
}

final class ResourceViewTestUnknownViewResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->include('source_depot', ExampleDepotResource::class, view: 'missing');
    }
}

final class ResourceViewTestCycleResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->include('peer', self::class, view: 'ring')
            ->view('ring', fields: 'id', include: 'peer');
    }
}

final class ResourceViewTestFixedSubjectResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id')->include('owner', ResourceViewTestLeafResource::class, view: 'reference');
    }
}

final class ResourceViewTestFreeSubjectResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id')->include('owner', ResourceViewTestLeafResource::class);
    }
}

final class ResourceViewTestSubjectResolver implements ResourceResolver
{
    public function resourceFor(Model $model): string
    {
        return ResourceViewTestFixedSubjectResource::class;
    }

    public function resources(): array
    {
        return [ResourceViewTestFixedSubjectResource::class, ResourceViewTestFreeSubjectResource::class];
    }
}

final class ResourceViewTestMixedResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id')->include('subject', ResourceViewTestSubjectResolver::class);
    }
}

/**
 * A definition with a field, an include clients select freely and an include fixed by a view.
 */
function resourceViewTestDefinition(): ResourceDefinition
{
    return (new ResourceDefinition)
        ->field('id')
        ->field('name')
        ->include('lines', ExampleTransferLineResource::class)
        ->include('owner', ResourceViewTestLeafResource::class, view: 'reference');
}

it('parses a view at freeze and keeps it apart from the members', function (): void {
    $definition = resourceViewTestDefinition()
        ->view('summary', fields: 'name', include: 'lines(sku),owner')
        ->freeze(ResourceViewTestLeafResource::class);

    expect($definition->viewNames())->toBe(['summary'])
        ->and($definition->members())->toBe(['id', 'name', 'lines', 'owner'])
        ->and($definition->includeView('owner'))->toBe('reference')
        ->and($definition->includeView('lines'))->toBeNull()
        ->and($definition->viewSelection('summary')?->toArray())->toBe([
            'fields'  => ['name'],
            'include' => ['lines' => ['fields' => ['sku'], 'include' => []], 'owner' => ['fields' => null, 'include' => []]],
        ]);
});

it('rejects a view declared twice at freeze and names the resource', function (): void {
    resourceViewTestDefinition()
        ->view('summary', fields: 'name')
        ->view('summary', fields: 'id')
        ->freeze(ResourceViewTestLeafResource::class);
})->throws(ResponseSelectionException::class, ResourceViewTestLeafResource::class.' declares view [summary] twice');

it('rejects a view name that is not snake_case', function (string $name): void {
    (new ResourceDefinition)->view($name, fields: 'id');
})->with(['Summary', 'sum-mary', '_summary', 'summary_'])->throws(ResponseSelectionException::class, 'is not a valid view name');

it('rejects a view: name that is not snake_case', function (): void {
    (new ResourceDefinition)->include('owner', ResourceViewTestLeafResource::class, view: 'Reference');
})->throws(ResponseSelectionException::class, '[Reference] is not a valid view name');

it('rejects a view declared wrong at its first level at freeze', function (string $fields, string $include, string $message): void {
    expect(fn () => resourceViewTestDefinition()
        ->view('summary', fields: $fields, include: $include)
        ->freeze(ResourceViewTestLeafResource::class))
        ->toThrow(ResponseSelectionException::class, $message);
})->with([
    'empty fields'               => ['', '', 'view [summary] lists no fields'],
    'blank fields'               => [' , ', '', 'view [summary] lists no fields'],
    'include without its list'   => ['name', 'lines', 'include [lines] needs its own field list'],
    'prefix without its list'    => ['name', 'lines.product(name)', 'include [lines] needs its own field list'],
    'unknown field'              => ['name,colour', '', '[colour] is not a field'],
    'include named in fields'    => ['lines', '', '[lines] is not a field'],
    'unknown include'            => ['name', 'author(name)', '[author] is not an include'],
    'fixed include with a list'  => ['name', 'owner(name)', 'include [owner] always renders its own view'],
    'fixed include with a child' => ['name', 'owner.owner', 'include [owner] always renders its own view'],
    'invalid syntax'             => ['name(id)', '', 'is not a valid selection'],
]);

it('applies the configured limits to the text of a view', function (): void {
    config(['response-selection.limits.max_fields_per_node' => 1]);

    resourceViewTestDefinition()
        ->view('summary', fields: 'id,name')
        ->freeze(ResourceViewTestLeafResource::class);
})->throws(ResponseSelectionException::class, 'At most 1 fields per node are allowed');

it('rejects view: on an include whose Resource a resolver picks', function (): void {
    (new ResourceDefinition)
        ->include('subject', ResourceViewTestSubjectResolver::class, view: 'reference')
        ->freeze(ResourceViewTestMixedResource::class);
})->throws(ResponseSelectionException::class, 'include [subject] names a view but a ResourceResolver picks its Resource');

it('rejects in validateResource() and on first use a view naming an unknown field of a child', function (): void {
    expect(fn () => (new ResponseDeclarationAnalyzer)->validateResource(ResourceViewTestUnknownChildFieldResource::class))
        ->toThrow(ResponseDeclarationException::class, 'view [broken] could not be built')
        ->and(fn () => (new SelectionViews)->tree(ResourceViewTestUnknownChildFieldResource::class, 'broken'))
        ->toThrow(ResponseSelectionException::class, ResourceViewTestUnknownChildFieldResource::class.' view [broken]: [lines.colour] is not a field.');
});

it('rejects in validateResource() and on first use a view: the child does not declare', function (): void {
    expect(fn () => (new ResponseDeclarationAnalyzer)->validateResource(ResourceViewTestUnknownViewResource::class))
        ->toThrow(ResponseDeclarationException::class, 'view [missing] could not be built')
        ->and(fn (): array => $this->renderApiResource(new ResourceViewTestModel(['id' => 1]), ResourceViewTestUnknownViewResource::class, 'id', 'source_depot'))
        ->toThrow(ResponseSelectionException::class, ExampleDepotResource::class.' declares no view [missing].');
});

it('rejects views including each other in a cycle', function (): void {
    expect(fn () => (new ResponseDeclarationAnalyzer)->validateResource(ResourceViewTestCycleResource::class))
        ->toThrow(ResponseDeclarationException::class, 'view [ring] could not be built')
        ->and(fn () => (new SelectionViews)->tree(ResourceViewTestCycleResource::class, 'ring'))
        ->toThrow(ResponseSelectionException::class, 'Views include each other in a cycle');
});

it('rejects an include fixed by a view in one Resource of a level and declared otherwise in another', function (): void {
    $tree = (new SelectionParser)->parse('id', 'subject.owner');

    expect(fn () => (new ResponseDeclarationAnalyzer)->validateResource(ResourceViewTestMixedResource::class))
        ->toThrow(ResponseDeclarationException::class, 'include [owner] could not be checked')
        ->and(fn () => app(SelectionValidator::class)->validate($tree->root(), ResourceViewTestMixedResource::class))
        ->toThrow(ResponseSelectionException::class, 'Include [owner] renders a view in some of');
});

it('accepts in validateResource() the views of a Resource and of its fixed includes', function (): void {
    (new ResponseDeclarationAnalyzer)->validateResource(ResourceViewTestFixedSubjectResource::class);

    expect((new SelectionViews)->tree(ResourceViewTestLeafResource::class, 'reference')->toArray())
        ->toBe(['fields' => ['id', 'name'], 'include' => []]);
});
