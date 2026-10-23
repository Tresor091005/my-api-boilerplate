<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response\Scramble;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as OpenApiObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Lahatre\Shared\Enums\ResponseMode;
use Lahatre\Shared\Enums\ResponseShape;
use Lahatre\Shared\Http\Response\ApiResource;
use Lahatre\Shared\Http\Response\ResourceDefinition;
use Lahatre\Shared\Http\Response\ResponseDeclarationRegistry;
use Lahatre\Shared\Http\Response\SelectionParser;

/**
 * Documents actions the ResponseDeclarationRegistry declares, that is, rendering through ApiResponse.
 * Reads the Resource, statuses and JSON shape from the explicit ApiResponse::item() / list() /
 * cursorPage() call, or noContent() alone, without executing the controller. The resource schema comes from the definition, the only
 * source of rendering: every member in declaration order, includes inlined up to
 * DOCUMENTED_INCLUDE_DEPTH.
 */
class ResponseSelectionOperationExtension extends OperationExtension
{
    private const DOCUMENTED_INCLUDE_DEPTH = 2;

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $declaration = app(ResponseDeclarationRegistry::class)->for($routeInfo->route);
        if ($declaration === null) {
            return;
        }

        $method = strtoupper($operation->method);
        $allowed = ResponseMode::allowedFor($method, $declaration->isNoContentOnly());
        $default = $allowed[0];

        $operation->addParameters([
            Parameter::make('response', 'query')
                ->setSchema(Schema::fromType((new StringType)->enum(array_map(fn (ResponseMode $mode) => $mode->value, $allowed))->default($default->value)))
                ->description("Response mode. Default `{$default->value}`; `none` answers 204 No Content."),
        ]);

        $operation->responses = array_values(array_filter(
            $operation->responses ?? [],
            fn ($response) => !$this->isSuccessResponse($response)
        ));

        if (in_array(ResponseMode::None, $allowed, true)) {
            $operation->addResponse(Response::make(204)->setDescription('No Content (response mode `none`).'));
        }

        $resourceClass = $declaration->resourceClass;
        if ($resourceClass === null || $declaration->shape === null || !in_array(ResponseMode::Resource, $allowed, true)) {
            return;
        }

        if ($declaration->canBeEmpty && !in_array(ResponseMode::None, $allowed, true)) {
            $operation->addResponse(Response::make(204)->setDescription('No Content (returned by the action).'));
        }
        $definition = $resourceClass::definition();

        $operation->addParameters([
            Parameter::make('fields', 'query')
                ->setSchema(Schema::fromType(new StringType))
                ->description('Comma-separated JSON keys of the root resource (`id` is always rendered). Available: '
                    .implode(', ', array_map(fn (string $key) => "`{$key}`", $definition->fieldKeys())).'.'),
            Parameter::make('include', 'query')
                ->setSchema(Schema::fromType(new StringType))
                ->description('Comma-separated include paths, each optionally followed by its fields in parentheses: '
                    .'`variants(id,sku),variants.optionValues(id,name)`. Max depth '.SelectionParser::MAX_DEPTH
                    .', max '.SelectionParser::MAX_PATHS.' paths. Available: '
                    .implode(', ', array_map(fn (string $path) => "`{$path}`", $this->includePaths($resourceClass))).'.'),
        ]);

        $resourceSchema = $this->resourceSchema($resourceClass);
        $body = match ($declaration->shape) {
            ResponseShape::Item       => (new OpenApiObjectType)->addProperty('data', $resourceSchema)->setRequired(['data']),
            ResponseShape::List       => (new OpenApiObjectType)->addProperty('data', (new ArrayType)->setItems($resourceSchema))->setRequired(['data']),
            ResponseShape::CursorPage => (new OpenApiObjectType)
                ->addProperty('data', (new ArrayType)->setItems($resourceSchema))
                ->addProperty('meta', $this->paginationMeta())
                ->setRequired(['data', 'meta']),
        };

        $description = $method === 'GET' || $method === 'HEAD'
            ? 'Resource.'
            : 'Resource (only with `response=resource`).';

        foreach ($declaration->statuses as $code) {
            $operation->addResponse(
                Response::make($code)
                    ->setDescription($description)
                    ->setContent('application/json', Schema::fromType($body))
            );
        }
    }

    private function paginationMeta(): OpenApiObjectType
    {
        return (new OpenApiObjectType)
            ->addProperty('per_page', new IntegerType)
            ->addProperty('next_cursor', (new StringType)->nullable(true))
            ->addProperty('prev_cursor', (new StringType)->nullable(true))
            ->setRequired(['per_page', 'next_cursor', 'prev_cursor']);
    }

    private function isSuccessResponse(mixed $response): bool
    {
        if ($response instanceof Reference) {
            $response = $response->resolve();
        }

        return $response instanceof Response && (int) $response->code >= 200 && (int) $response->code < 300;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<int, string>
     */
    private function includePaths(string $resourceClass, string $prefix = '', int $depth = 1): array
    {
        if ($depth > self::DOCUMENTED_INCLUDE_DEPTH) {
            return [];
        }

        $definition = $resourceClass::definition();
        $paths = [];

        foreach ($definition->includeNames() as $name) {
            $path = $prefix === '' ? $name : "{$prefix}.{$name}";
            $paths[] = $path;

            foreach ($definition->includeResourceClasses($name) as $childClass) {
                array_push($paths, ...$this->includePaths($childClass, $path, $depth + 1));
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Every member a Resource may render, in declaration order: a field's type is unknown to the
     * definition, an include renders its child Resources. Only `id` is required: the selection and
     * `when:` decide the other keys.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, class-string<ApiResource>>  $stack  Resources being documented (recursion guard)
     */
    private function resourceSchema(string $resourceClass, int $depth = 0, array $stack = []): OpenApiObjectType
    {
        $definition = $resourceClass::definition();
        $schema = new OpenApiObjectType;

        foreach ($definition->members() as $name) {
            $schema->addProperty($name, $definition->hasField($name)
                ? new MixedType
                : $this->includeSchema($definition, $name, $depth, [...$stack, $resourceClass]));
        }

        if ($definition->hasField('id') && $definition->condition('id') === null) {
            $schema->setRequired(['id']);
        }

        return $schema;
    }

    /**
     * @param  array<int, class-string<ApiResource>>  $stack
     */
    private function includeSchema(ResourceDefinition $definition, string $name, int $depth, array $stack): Type
    {
        $children = array_map(
            fn (string $child) => $depth < self::DOCUMENTED_INCLUDE_DEPTH && !in_array($child, $stack, true)
                ? $this->resourceSchema($child, $depth + 1, $stack)
                : new OpenApiObjectType,
            $definition->includeResourceClasses($name),
        );
        $child = count($children) === 1 ? $children[0] : (new AnyOf)->setItems($children);

        // nullable() is not serialized on an AnyOf: null is an explicit branch
        if ($definition->isVirtualInclude($name)) {
            return $definition->rendersAsList($name) ? (new ArrayType)->setItems($child) : (new AnyOf)->setItems([...$children, new NullType]);
        }

        // the cardinality comes from the Eloquent relation, unknown to the definition
        return (new AnyOf)
            ->setItems([...$children, new NullType, (new ArrayType)->setItems($child)])
            ->setDescription('One child (or null) or a list, as the relation defines.');
    }
}
