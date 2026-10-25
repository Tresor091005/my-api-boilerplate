<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Scramble;

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
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationRegistry;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\Selection\SelectionParser;

/**
 * Completes the API documentation of every route that answers with ApiResponse: the `response`,
 * `fields` and `include` parameters, and what the body looks like.
 */
class ResponseSelectionOperationExtension extends OperationExtension
{
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
                    .$this->fieldList($definition).'.'),
            Parameter::make('include', 'query')
                ->setSchema(Schema::fromType(new StringType))
                ->description('Comma-separated include paths, each optionally followed by its fields in parentheses: '
                    .'`variants(id,sku),variants.labels(value)`. Each level offers the includes of its Resource schema, up to '
                    .SelectionParser::maxDepth().' levels and '.SelectionParser::maxPaths().' paths. Available here: '
                    .implode(', ', array_map(fn (string $name) => "`{$name}`", $definition->includeNames())).'.'),
        ]);

        $resourceSchema = $this->resourceReference($resourceClass);
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
     * The field keys clients may list, those rendered only on request marked as such.
     */
    private function fieldList(ResourceDefinition $definition): string
    {
        return implode(', ', array_map(
            fn (string $key) => $definition->rendersByDefault($key) ? "`{$key}`" : "`{$key}` (only when listed)",
            $definition->fieldKeys(),
        ));
    }

    /**
     * The schema of a Resource, written once in the document's shared schemas and referenced
     * everywhere else, so every include level is documented and loops cost nothing.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    private function resourceReference(string $resourceClass): Reference
    {
        $components = $this->openApiTransformer->getComponents();

        if ($components->hasSchema($resourceClass)) {
            return $components->getSchemaReference($resourceClass);
        }

        // registered before it is described, so a Resource that includes itself refers back to it
        $schema = new OpenApiObjectType;
        $reference = $components->addSchema($resourceClass, Schema::fromType($schema));
        $this->describeResource($resourceClass, $schema);

        return $reference;
    }

    /**
     * Describes the body of a Resource. Only `id` is marked required, since the client picks the other keys.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    private function describeResource(string $resourceClass, OpenApiObjectType $schema): void
    {
        $definition = $resourceClass::definition();

        foreach ($definition->members() as $name) {
            $schema->addProperty($name, $definition->hasField($name) ? new MixedType : $this->includeSchema($definition, $name));
        }

        if ($definition->hasField('id') && $definition->condition('id') === null) {
            $schema->setRequired(['id']);
        }
    }

    private function includeSchema(ResourceDefinition $definition, string $name): Type
    {
        $children = array_map($this->resourceReference(...), $definition->includeResourceClasses($name));
        $child = count($children) === 1 ? $children[0] : (new AnyOf)->setItems($children);

        // An AnyOf cannot be marked nullable, so null is added as one of its options
        if ($definition->isVirtualInclude($name)) {
            return $definition->rendersAsList($name) ? (new ArrayType)->setItems($child)->setMax($definition->includeLimit($name)) : (new AnyOf)->setItems([...$children, new NullType]);
        }

        // One item or a list depends on the Eloquent relation, which the definition does not know
        return (new AnyOf)
            ->setItems([...$children, new NullType, (new ArrayType)->setItems($child)->setMax($definition->includeLimit($name))])
            ->setDescription('One child (or null) or a list, as the relation defines.');
    }
}
