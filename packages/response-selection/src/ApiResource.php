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
use Illuminate\Support\Collection;
use JsonSerializable;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\Rendering\JsonDate;
use Lahatre\ResponseSelection\Rendering\ModelState;
use Lahatre\ResponseSelection\Rendering\RenderStep;
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
        return self::newCollection($resource);
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
     *
     * @return array<string, mixed>
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
        self::definition();
        $selection = $this->attachedSelection();
        $model = $this->model();
        $context = app(ResponseContext::class);
        $plan = $context->renderPlan(static::class, $selection, $request);
        $state = $plan->readsConditions ? $context->stateFor($model) : $context->stateOf($model);
        $data = [];

        foreach ($plan->steps as $step) {
            foreach ($step->checks as [$isCondition, $key, $when]) {
                if ($isCondition) {
                    if (!($state?->conditions[$key] ?? $context->acceptsModel($key, $when, $model, $request))) {
                        continue 2;
                    }
                } elseif ($state !== null && isset($state->absent[$key]) && !array_key_exists($key, $state->values)) {
                    // its FieldComputer ran for this model and gave it no value
                    continue 2;
                }
            }

            $data[$step->name] = $step->isField
                ? $this->fieldValue($state, $model, $step)
                : $this->renderInclude($state, $model, $step);
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

    private function fieldValue(?ModelState $state, Model $model, RenderStep $step): mixed
    {
        $key = $step->name;
        $slot = $step->slot;

        if ($slot === null) {
            $value = $model->getAttribute($key);
        } elseif ($state !== null && array_key_exists($slot, $state->values)) {
            $value = $state->values[$slot];
        } elseif ($state !== null && isset($state->absent[$slot])) {
            $value = null;
        } else {
            throw ResponseSelectionException::computedFieldNotComputed(static::class, $key);
        }

        $this->assertFieldValue($key, $value);

        return JsonDate::serialize($value);
    }

    /**
     * Renders an include, from the loaded relation or from the children its loader built.
     */
    private function renderInclude(?ModelState $state, Model $model, RenderStep $step): mixed
    {
        if ($step->isVirtual) {
            $children = $state?->virtual[static::class] ?? [];

            if (!array_key_exists($step->name, $children)) {
                throw ResponseSelectionException::includeNotLoaded(static::class, $step->name);
            }

            return $this->renderChild($step, $children[$step->name]);
        }

        $relation = (string) $step->relation;

        if (!$model->relationLoaded($relation)) {
            throw ResponseSelectionException::includeNotLoaded(static::class, $step->name);
        }

        return $this->renderChild($step, $model->getRelation($relation));
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
    private function renderChild(RenderStep $step, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $resourceClass = $step->childClass;
        // built once per response, when the first child renders
        $resolver = $resourceClass === null ? ($step->resolver ??= self::definition()->includeResolver($step->name)) : null;
        $childSelection = $step->child ?? new SelectionNode;

        if ($step->isVirtual ? $step->asList : $value instanceof Collection) {
            // drop the keys so the children render as a JSON array, not an object
            $items = collect($value)->values()->take($step->limit);

            if ($resourceClass !== null) {
                /** @var AnonymousResourceCollection $collection */
                $collection = $resourceClass::collection($items);

                return self::attachSelectionToEach($collection, $childSelection);
            }

            // a child the resolver gives no Resource is left out of the list
            return $items->map(fn (Model $item): ?ApiResource => $this->makeChild($step, $resolver, null, $item, $childSelection))
                ->filter(fn (?ApiResource $child): bool => $child !== null)
                ->values()
                ->all();
        }

        return $this->makeChild($step, $resolver, $resourceClass, $value, $childSelection);
    }

    /**
     * @param  class-string<ApiResource>|null  $resourceClass
     */
    private function makeChild(RenderStep $step, ?ResourceResolver $resolver, ?string $resourceClass, Model $item, SelectionNode $childSelection): ?ApiResource
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

            if (!in_array($class, $step->resolverResources ??= $resolver->resources(), true)) {
                throw ResponseSelectionException::resolverResourceNotListed($resolver::class, $class);
            }
        }

        return new $class($item)->attachSelection($childSelection);
    }
}
