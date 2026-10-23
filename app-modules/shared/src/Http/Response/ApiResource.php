<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

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
use Lahatre\Shared\Exceptions\ResponseSelectionException;
use stdClass;
use Traversable;

/**
 * Base class of every Resource rendered by ApiResponse. A Resource only implements define():
 * toArray() is generated from the definition.
 *
 * It renders an Eloquent model only, with a SelectionNode attached by ApiResponse or by a parent
 * include: the selected definition fields (+ `id`) and the selected includes, in declaration
 * order, minus the members that do not exist for the model. Rendered without one (`new XResource($model)`), it throws: a Resource
 * rendered outside ApiResponse stays a JsonResource.
 *
 * Rendering never queries: dependencies are loaded and built beforehand by SelectionLoader. The
 * rendering pipeline is final, so no subclass renders keys outside its definition.
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
     * Declares the fields and includes this Resource can render. Only definition() calls it, once
     * per class: a subclass of another Resource calls parent::define($resource), then adds to it.
     */
    abstract protected static function define(ResourceDefinition $resource): void;

    /**
     * The definition, built once per class by define(), then frozen and shared by every render.
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
     * Attaches the selection this Resource renders; set once, by ApiResponse or a parent include.
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
     * Attaches a selection node to every item of a resource collection.
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
     * Laravel's rendering pipeline, redeclared to be final so no Resource renders keys outside
     * toArray(). Laravel calls these methods itself while serializing a Resource, which is why
     * application code rarely calls them.
     */

    /**
     * Wraps items in an AnonymousResourceCollection of this Resource (X::collection()). Unlike
     * Laravel, ignores #[PreserveKeys] and $preserveKeys: a list always renders as a JSON list.
     */
    final public static function collection($resource)
    {
        return static::newCollection($resource);
    }

    /**
     * Builds the collection returned by collection(): no Resource substitutes its own class.
     */
    final protected static function newCollection($resource)
    {
        return new AnonymousResourceCollection($resource, static::class);
    }

    /**
     * Renders the Resource to an array: resolveResourceData(), then filter(). Unchanged from Laravel.
     */
    final public function resolve($request = null)
    {
        return parent::resolve($request);
    }

    /**
     * The data resolve() renders. Laravel reads it through toAttributes(); here it is always
     * toArray(), generated from the definition.
     */
    final public function resolveResourceData(Request $request)
    {
        return $this->toArray($request);
    }

    /**
     * Laravel's alternative to toArray(): an $attributes property may replace it. Always toArray()
     * here, so no property renders keys outside the definition and the selection.
     */
    final public function toAttributes(Request $request)
    {
        return $this->toArray($request);
    }

    /**
     * Called by json_encode() and JSON responses: delegates to resolve(). Unchanged from Laravel.
     */
    final public function jsonSerialize(): array
    {
        return parent::jsonSerialize();
    }

    /**
     * Cleans the array toArray() returned, recursively: merges mergeWhen() values through
     * mergeData(), renders a child Resource without a model as null, then removeMissingValues().
     * Unchanged from Laravel.
     */
    final protected function filter($data)
    {
        return parent::filter($data);
    }

    /**
     * Inserts the values of a mergeWhen() at its position in the array. Unchanged from Laravel.
     */
    final protected function mergeData($data, $index, $merge, $numericKeys)
    {
        return parent::mergeData($data, $index, $merge, $numericKeys);
    }

    /**
     * Drops missing values: a field that is not selected, a when() whose condition failed, an
     * unloaded relation. Unchanged from Laravel.
     */
    final protected function removeMissingValues($data)
    {
        return parent::removeMissingValues($data);
    }

    /**
     * Renders the members of the definition, in declaration order (a parent Resource's first): each
     * selected field (and `id`) and each selected include that exists for the model (its `when:`
     * accepts it and every member of its `fields:` exists). A field's value comes from its
     * computedBy builder (kept in ResponseContext) or, without one, from the attribute of its key;
     * a field its FieldComputer left out does not exist.
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

    /**
     * The engine renders Eloquent models only.
     */
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
     * Renders an include with its child Resource: an Eloquent relation loaded on the model, or the
     * children of a virtual include kept in ResponseContext.
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
     * A field renders data, never another rendering unit: a model, a Resource or a page would be
     * serialized whole, outside every definition and selection. Those are includes.
     */
    private function assertFieldValue(string $key, mixed $value, int $depth = 0): void
    {
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
     * Renders the children of an include: a list when ResourceDefinition::rendersChildList() says
     * so, without the children a resolver gives no Resource, otherwise one child or null.
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
            // children render as a list: keys of a keyed collection are data, not JSON keys
            $items = collect($value)->values();

            if ($resourceClass !== null) {
                /** @var AnonymousResourceCollection $collection */
                $collection = $resourceClass::collection($items);

                return self::attachSelectionToEach($collection, $childSelection);
            }

            // a child without API representation is dropped: a list holds objects only
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

            // a single child without API representation renders null, as a missing child does
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
