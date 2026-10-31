<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\Scramble\ResponseSelectionOperationExtension;
use ReflectionClass;
use ReflectionMethod;

final class OpenApiSchemaTestChildResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id');
    }
}

final class OpenApiSchemaTestParentResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('summary', columns: [], computedBy: fn (): string => 'summary', default: false)
            ->include('lines', OpenApiSchemaTestChildResource::class, limit: 2)
            ->virtualInclude('peers', OpenApiSchemaTestChildResource::class, fn (): array => [], asList: true, limit: 3);
    }
}

/**
 * A Resource including itself, as a category tree does.
 */
final class OpenApiSchemaTestTreeResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->include('parent', OpenApiSchemaTestTreeResource::class);
    }
}

/**
 * The shared schemas the extension writes for a Resource and every Resource it includes, by name.
 *
 * @param  class-string<ApiResource>  $resourceClass
 * @return array<string, array<string, mixed>>
 */
function openApiResourceSchemas(string $resourceClass): array
{
    $openApi = OpenApi::make('3.1.0');
    $config = new GeneratorConfig;
    $transformer = new TypeTransformer(app(Infer::class), new OpenApiContext($openApi, $config));
    $extension = new ResponseSelectionOperationExtension(app(Infer::class), $transformer, $config);

    new ReflectionMethod($extension, 'resourceReference')->invoke($extension, $resourceClass);

    return $openApi->components->toArray()['schemas'] ?? [];
}

it('describes each Resource once and points every include to its schema', function (): void {
    $schemas = openApiResourceSchemas(OpenApiSchemaTestParentResource::class);
    $child = ['$ref' => '#/components/schemas/OpenApiSchemaTestChildResource'];

    expect(array_keys($schemas))->toBe(['OpenApiSchemaTestChildResource', 'OpenApiSchemaTestParentResource'])
        ->and($schemas['OpenApiSchemaTestParentResource']['properties']['peers']['items'])->toBe($child)
        ->and($schemas['OpenApiSchemaTestParentResource']['properties']['lines']['anyOf'][0])->toBe($child);
});

it('documents the include limit as the most items of a list include', function (): void {
    $properties = openApiResourceSchemas(OpenApiSchemaTestParentResource::class)['OpenApiSchemaTestParentResource']['properties'];

    expect($properties['peers']['maxItems'] ?? null)->toBe(3)
        ->and($properties['lines']['anyOf'][2]['maxItems'] ?? null)->toBe(2);
});

it('documents a Resource that includes itself by pointing back to its own schema', function (): void {
    $schemas = openApiResourceSchemas(OpenApiSchemaTestTreeResource::class);

    expect(array_keys($schemas))->toBe(['OpenApiSchemaTestTreeResource'])
        ->and($schemas['OpenApiSchemaTestTreeResource']['properties']['parent']['anyOf'][0])
        ->toBe(['$ref' => '#/components/schemas/OpenApiSchemaTestTreeResource']);
});

it('marks the fields rendered only when listed in the fields parameter', function (): void {
    $extension = new ReflectionClass(ResponseSelectionOperationExtension::class)->newInstanceWithoutConstructor();
    $list = new ReflectionMethod($extension, 'fieldList')->invoke($extension, OpenApiSchemaTestParentResource::definition());

    expect($list)->toBe('`id`, `summary` (only when listed)');
});
