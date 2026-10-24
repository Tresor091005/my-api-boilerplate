<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use JsonSerializable;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\Selection\SelectionNode;
use stdClass;
use Traversable;

/**
 * Base class of the Resources that ApiResponse renders. A Resource only declares its fields and
 * includes in define(); it then renders those the client asked for.
 */
abstract class ApiResource extends JsonResource
{
    /**
     * @var array<class-string<ApiResource>, ResourceDefinition>
     */
    private static array $definitions = [];

    /**
     * @var array<class-string<ApiResource>, true>
     */
    private static array $building = [];

    private ?SelectionNode $selection = null;

    /**
     * Declares the fields and includes this Resource can render. A Resource extending another one
     * calls parent::define($resource) first.
     */
    abstract protected static function define(ResourceDefinition $resource): void;

    /**
     * What this Resource declared in define(), built once and then shared.
     */
    final public static function definition(): ResourceDefinition
    {
        if (isset(self::$definitions[static::class])) {
            return self::$definitions[static::class];
        }

        if (isset(self::$building[static::class])) {
            throw ResponseSelectionException::definitionRecursion(static::class);
        }

        self::$building[static::class] = true;

        try {
            $definition = new ResourceDefinition;
            static::define($definition);

            return self::$definitions[static::class] = $definition->freeze(static::class);
        } finally {
            unset(self::$building[static::class]);
        }
    }

    /**
     * Sets which fields and includes this Resource renders. It can only be set once.
     */
    final public function attachSelection(SelectionNode $selection): static
    {
        if ($this->selection !== null && $selection !== $this->selection) {
            throw ResponseSelectionException::selectionAlreadySet(static::class);
        }

        $this->selection = $selection;

        return $this;
    }

    final public function selection(): ?SelectionNode
    {
        return $this->selection;
    }

    /**
     * Sets which fields and includes every Resource of the collection renders.
     *
     * @internal
     */
    final public static function attachSelectionToEach(ResourceCollection $collection, SelectionNode $selection): ResourceCollection
    {
        $collection->collection->each(function ($item) use ($selection): void {
            if ($item instanceof ApiResource) {
                $item->attachSelection($selection);
            }
        });

        return $collection;
    }

    /*
     * Laravel's own rendering methods, made final so no Resource can render anything it did not declare.
     */

    /**
     * Wraps models in a collection of this Resource. Unlike Laravel, keys are never kept: a list
     * always renders as a JSON array.
     */
    final public static function collection($resource)
    {
        return static::newCollection($resource);
    }

    final protected static function newCollection($resource)
    {
        return new AnonymousResourceCollection($resource, static::class);
    }

    final public function resolve($request = null)
    {
        return parent::resolve($request);
    }

    final public function resolveResourceData(Request $request)
    {
        return $this->toArray($request);
    }

    /**
     * Ignores Laravel's `$attributes` property: only what define() declares is rendered.
     */
    final public function toAttributes(Request $request)
    {
        return $this->toArray($request);
    }

    final public function jsonSerialize(): array
    {
        return parent::jsonSerialize();
    }

    final protected function filter($data)
    {
        return parent::filter($data);
    }

    final protected function mergeData($data, $index, $merge, $numericKeys)
    {
        return parent::mergeData($data, $index, $merge, $numericKeys);
    }

    final protected function removeMissingValues($data)
    {
        return parent::removeMissingValues($data);
    }

    /**
     * Renders `id` and the fields and includes the client asked for, in declaration order, skipping
     * those that do not apply to this model.
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        $definition = static::definition();
        $selection = $this->attachedSelection();
        $model = $this->model();
        $context = app(ResponseContext::class);
        $data = [];

        foreach ($definition->members() as $name) {
            if ($definition->hasField($name)) {
                if (($name === 'id' || $selection->wantsField($name)) && $context->exists($definition, static::class, $name, $model, $request)) {
                    $data[$name] = $this->fieldValue($context, $model, $name);
                }

                continue;
            }

            $childSelection = $selection->child($name);

            if ($childSelection !== null && $context->exists($definition, static::class, $name, $model, $request)) {
                $data[$name] = $this->renderInclude($context, $model, $name, $childSelection);
            }
        }

        return $data;
    }

    private function attachedSelection(): SelectionNode
    {
        return $this->selection ?? throw ResponseSelectionException::renderedWithoutSelection(static::class);
    }

    private function model(): Model
    {
        return $this->resource instanceof Model
            ? $this->resource
            : throw ResponseSelectionException::notAModel(static::class, get_debug_type($this->resource));
    }

    private function fieldValue(ResponseContext $context, Model $model, string $key): mixed
    {
        $builder = static::definition()->builderOf(static::class, $key);

        if ($builder === null) {
            $value = $model->getAttribute($key);
        } elseif ($context->wasComputed($model, $builder, $key)) {
            $value = $context->computedValue($model, $builder, $key);
        } else {
            throw ResponseSelectionException::computedFieldNotComputed(static::class, $key);
        }

        $this->assertFieldValue($key, $value);

        return $value;
    }

    /**
     * Renders an include, from the loaded relation or from the children its loader built.
     */
    private function renderInclude(ResponseContext $context, Model $model, string $name, SelectionNode $childSelection): mixed
    {
        $definition = static::definition();

        if ($definition->isVirtualInclude($name)) {
            if (!$context->hasVirtual($model, static::class, $name)) {
                throw ResponseSelectionException::includeNotLoaded(static::class, $name);
            }

            return $this->renderChild($name, $context->virtualValue($model, static::class, $name), $childSelection);
        }

        $relation = (string) $definition->includeRelation($name);

        if (!$model->relationLoaded($relation)) {
            throw ResponseSelectionException::includeNotLoaded(static::class, $name);
        }

        return $this->renderChild($name, $model->getRelation($relation), $childSelection);
    }

    /**
     * Refuses a model, Resource or page as a field value: it would be rendered whole. Use an include.
     */
    private function assertFieldValue(string $key, mixed $value, int $depth = 0): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if ($value instanceof Model || $value instanceof JsonResource || $value instanceof AbstractPaginator || $value instanceof AbstractCursorPaginator) {
            throw ResponseSelectionException::fieldRendersRenderingUnit(static::class, $key, $value::class);
        }

        if ($depth >= 32 || $value instanceof DateTimeInterface || $value instanceof Generator) {
            return;
        }

        // Fluent and collections are Traversable
        $nested = match (true) {
            is_array($value), $value instanceof Traversable => $value,
            $value instanceof JsonSerializable              => [$value->jsonSerialize()],
            $value instanceof stdClass                      => get_object_vars($value),
            default                                         => [],
        };

        foreach ($nested as $item) {
            $this->assertFieldValue($key, $item, $depth + 1);
        }
    }

    /**
     * Renders the value of an include: one child, a list of children, or null.
     */
    private function renderChild(string $name, mixed $value, SelectionNode $childSelection): mixed
    {
        if ($value === null) {
            return null;
        }

        $definition = static::definition();
        $resourceClass = $definition->includeResourceClass($name);
        $resolver = $resourceClass === null ? $definition->includeResolver($name) : null;

        if ($definition->rendersChildList($name, $value)) {
            // drop the keys so the children render as a JSON array, not an object
            $items = collect($value)->values();

            if ($resourceClass !== null) {
                /** @var AnonymousResourceCollection $collection */
                $collection = $resourceClass::collection($items);

                return self::attachSelectionToEach($collection, $childSelection);
            }

            // a child the resolver gives no Resource is left out of the list
            return $items->map(fn (Model $item) => $this->makeChild($resolver, null, $item, $childSelection))
                ->filter(fn (?ApiResource $child) => $child !== null)
                ->values()
                ->all();
        }

        return $this->makeChild($resolver, $resourceClass, $value, $childSelection);
    }

    /**
     * @param  class-string<ApiResource>|null  $resourceClass
     */
    private function makeChild(?ResourceResolver $resolver, ?string $resourceClass, Model $item, SelectionNode $childSelection): ?ApiResource
    {
        $class = $resourceClass;

        if ($class === null) {
            if ($resolver === null) {
                throw ResponseSelectionException::childResourceUnresolvable(static::class);
            }

            $class = $resolver->resourceFor($item);

            // no Resource for this child: it renders as null
            if ($class === null) {
                return null;
            }

            if (!in_array($class, $resolver->resources(), true)) {
                throw ResponseSelectionException::resolverResourceNotListed($resolver::class, $class);
            }
        }

        return (new $class($item))->attachSelection($childSelection);
    }
}
