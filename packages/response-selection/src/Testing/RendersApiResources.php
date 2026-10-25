<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\Declaration\ResponseDeclaration;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\Selection\SelectionParser;
use Lahatre\ResponseSelection\Selection\SelectionValidator;

/**
 * Test helper that renders a model through its Resource without an HTTP request, to check what a
 * Resource shows for a given selection.
 */
trait RendersApiResources
{
    /**
     * Renders $model as ApiResponse::item() does for a request with these `fields` and `include`,
     * and returns its `data`. An unknown name fails as it would with a 422.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<string, mixed>
     */
    protected function renderApiResource(Model $model, string $resourceClass, ?string $fields = null, ?string $include = null): array
    {
        $tree = (new SelectionParser)->parse($fields, $include);
        app(SelectionValidator::class)->validate($tree->root(), $resourceClass);

        $context = app(ResponseContext::class);
        $context->start(ResponseMode::Resource, $tree, new ResponseDeclaration($resourceClass, ResponseShape::Item, [200]));

        try {
            $response = ApiResponse::item($model, $resourceClass);

            return $response instanceof JsonResponse ? $response->getData(true)['data'] : [];
        } finally {
            $context->reset();
        }
    }
}
