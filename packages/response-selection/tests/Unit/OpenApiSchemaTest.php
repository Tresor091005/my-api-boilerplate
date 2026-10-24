<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

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
            ->include('lines', OpenApiSchemaTestChildResource::class, limit: 2)
            ->virtualInclude('peers', OpenApiSchemaTestChildResource::class, fn () => [], asList: true, limit: 3);
    }
}

/**
 * The OpenAPI schema the extension describes for a Resource.
 *
 * @param  class-string<ApiResource>  $resourceClass
 * @return array<string, mixed>
 */
function openApiResourceSchema(string $resourceClass): array
{
    $extension = (new ReflectionClass(ResponseSelectionOperationExtension::class))->newInstanceWithoutConstructor();

    return (new ReflectionMethod($extension, 'resourceSchema'))->invoke($extension, $resourceClass)->toArray();
}

it('documents the include limit as the most items of a list include', function (): void {
    $properties = openApiResourceSchema(OpenApiSchemaTestParentResource::class)['properties'];

    expect($properties['peers']['maxItems'] ?? null)->toBe(3)
        ->and($properties['lines']['anyOf'][2]['maxItems'] ?? null)->toBe(2);
});
