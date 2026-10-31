<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\FieldComputer;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\ResourceResolver;
use Lahatre\ResponseSelection\Selection\SelectionNode;
use Lahatre\ResponseSelection\VirtualIncludeLoader;
use WeakMap;

/**
 * Loads and computes everything a response needs once the main query has run: missing columns,
 * relations, computed fields and included resources. The number of queries grows with the depth of
 * the response, never with the number of rows.
 *
 * Until release(), reading a relation that was not loaded throws instead of querying. Eloquent
 * exempts models not saved yet or created in this request, so their relations must be declared or
 * loaded beforehand.
 */
class SelectionLoader
{
    /**
     * Each model's lazy-loading setting before load(), to restore it afterwards.
     *
     * @var WeakMap<Model, bool>
     */
    protected WeakMap $lazyLoading;

    /** How many load() calls are running. Results are remembered only during a load. */
    private int $loading = 0;

    /** @var WeakMap<SelectionNode, array<string, array{0: array<int, string>, 1: array<int, string>}>> */
    private WeakMap $membersMemo;

    /** @var WeakMap<SelectionNode, array<string, array<int, string>>> */
    private WeakMap $columnsMemo;

    /** @var array<string, Relation> */
    private array $relationsMemo = [];

    /** @var array<string, Model> */
    private array $blanksMemo = [];

    private ?ResponseContext $contextMemo = null;

    private ?Request $requestMemo = null;

    public function __construct(protected Container $container)
    {
        $this->lazyLoading = new WeakMap;
        $this->membersMemo = new WeakMap;
        $this->columnsMemo = new WeakMap;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function load(Model|EloquentCollection $result, string $resourceClass, SelectionNode $selection): void
    {
        $this->loading++;

        try {
            $this->loadNode($result instanceof Model ? new EloquentCollection([$result]) : $result, $resourceClass, $selection);
        } finally {
            if (--$this->loading === 0) {
                $this->membersMemo = new WeakMap;
                $this->columnsMemo = new WeakMap;
                $this->relationsMemo = [];
                $this->blanksMemo = [];
                $this->contextMemo = null;
                $this->requestMemo = null;
            }
        }
    }

    /**
     * Returns an empty model of the same class, reused during one load.
     */
    protected function blank(Model $model): Model
    {
        if ($this->loading === 0) {
            return $model->newInstance();
        }

        return $this->blanksMemo[$model::class."\0".$model->getConnectionName()."\0".$model->getTable()] ??= $model->newInstance();
    }

    /**
     * Gives the models back their original lazy-loading setting once the response is rendered.
     */
    public function release(): void
    {
        foreach ($this->lazyLoading as $model => $previous) {
            $model->preventsLazyLoading = $previous;
        }

        $this->lazyLoading = new WeakMap;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function loadNode(EloquentCollection $items, string $resourceClass, SelectionNode $selection): void
    {
        $items = $this->unique($items);

        if ($items->isEmpty()) {
            return;
        }

        $definition = $resourceClass::definition();
        [$fields, $includes] = $this->members($resourceClass, $selection);
        $groups = $this->modelGroups($items);

        // protected before the per-model `when:` conditions read the models
        foreach ($groups as $group) {
            $this->loadDependencies($group, $resourceClass, $selection, $fields, $includes);
            $this->preventLazyLoading($group);
        }

        $accepted = $this->acceptedItems($items, $resourceClass, [...$fields, ...$includes]);

        $this->loadIncludes($resourceClass, $selection, $fields, $includes, $accepted);
        $this->preventLazyLoading($items);
        $this->buildFields($resourceClass, $fields, $accepted);

        foreach ($includes as $name) {
            $parents = $this->withDependencies($accepted[$name], $resourceClass, $name);

            if ($definition->isVirtualInclude($name)) {
                $this->loadVirtual($parents, $resourceClass, $name);
                $children = $this->virtualChildren($parents, $resourceClass, $name);
                $this->preventLazyLoading($children);
            } else {
                $children = $this->childrenOf($parents, $resourceClass, $name);
            }

            if ($children->isEmpty()) {
                continue;
            }

            $childSelection = $selection->child($name) ?? new SelectionNode;
            $childClass = $definition->includeResourceClass($name);

            if ($childClass !== null) {
                $this->loadNode($children, $childClass, $childSelection);

                continue;
            }

            $resolver = $definition->includeResolver($name);
            $resolved = [];

            if ($resolver === null) {
                throw ResponseSelectionException::childResourceUnresolvable($resourceClass);
            }

            foreach ($children as $child) {
                $class = $this->resolvedResource($resolver, $child);

                // a child without a resource renders null, so nothing is loaded under it
                if ($class !== null) {
                    $resolved[$class][] = $child;
                }
            }

            foreach ($resolved as $class => $group) {
                $this->loadNode(new EloquentCollection($group), $class, $childSelection);
            }
        }
    }

    /**
     * The fields to build and the includes to load for one level, leaving out what the request may
     * not see.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected function members(string $resourceClass, SelectionNode $selection): array
    {
        if ($this->loading === 0) {
            return $this->computeMembers($resourceClass, $selection);
        }

        $memo = $this->membersMemo[$selection] ?? [];

        if (!isset($memo[$resourceClass])) {
            $memo[$resourceClass] = $this->computeMembers($resourceClass, $selection);
            $this->membersMemo[$selection] = $memo;
        }

        return $memo[$resourceClass];
    }

    /**
     * Works out the fields and includes selected at this level, without memory.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected function computeMembers(string $resourceClass, SelectionNode $selection): array
    {
        $definition = $resourceClass::definition();
        $context = $this->context();
        $request = $this->request();
        $accepts = fn (string $name): bool => $context->existsForRequest($definition, $resourceClass, $name, $request);

        $includes = array_values(array_filter(
            $definition->includeNames(),
            fn (string $name): bool => $selection->hasChild($name) && $accepts($name)
        ));
        $requested = array_values(array_filter(
            $definition->fieldKeys(),
            fn (string $key) => $definition->selectsField($selection, $key)
        ));

        return [$definition->fieldsToBuild([...$requested, ...$includes], $accepts), $includes];
    }

    /**
     * Groups models by class, because Eloquent eager-loads a whole collection using its first model's
     * relations.
     *
     * @return array<int, EloquentCollection>
     */
    protected function modelGroups(EloquentCollection $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $groups[$item::class][] = $item;
        }

        return array_values(array_map(fn (array $models): EloquentCollection => new EloquentCollection($models), $groups));
    }

    /**
     * Turns lazy loading off on these models and on every model already loaded under them.
     */
    protected function preventLazyLoading(EloquentCollection $models): void
    {
        $pending = $models->all();
        $visited = [];

        while ($pending !== []) {
            $model = array_pop($pending);
            $id = spl_object_id($model);

            if (isset($visited[$id])) {
                continue;
            }

            $visited[$id] = true;

            if (!isset($this->lazyLoading[$model])) {
                $this->lazyLoading[$model] = $model->preventsLazyLoading;
                $model->preventsLazyLoading = true;
            }

            foreach ($model->getRelations() as $related) {
                if ($related instanceof Model) {
                    $pending[] = $related;
                } elseif ($related instanceof Collection) {
                    foreach ($related as $item) {
                        if ($item instanceof Model) {
                            $pending[] = $item;
                        }
                    }
                }
            }
        }
    }

    /**
     * Loads the columns and relations the requested fields read, on models of one class, and checks
     * that every relation used exists.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $includes
     */
    protected function loadDependencies(EloquentCollection $models, string $resourceClass, SelectionNode $selection, array $fields, array $includes): void
    {
        $definition = $resourceClass::definition();
        // a blank instance, because a model still missing its key column cannot build its relations
        $first = $this->blank($models->first());
        $paths = $this->relationPaths($definition, $fields, $includes);

        foreach ($paths as $path) {
            $this->assertRelationPath($first, $path, $resourceClass);
        }

        foreach ($includes as $name) {
            if (!$definition->isVirtualInclude($name)) {
                $this->relationFor($first, (string) $definition->includeRelation($name), $resourceClass);
            }
        }

        $this->completeColumns($models, $this->nodeColumns($first, $resourceClass, $selection));

        if ($paths !== []) {
            $models->loadMissing($paths);
        }
    }

    /**
     * Relations the given fields and virtual includes read.
     *
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $includes
     * @return array<int, string>
     */
    protected function relationPaths(ResourceDefinition $definition, array $fields, array $includes): array
    {
        $paths = [];

        foreach ($fields as $key) {
            array_push($paths, ...($definition->fieldDefinition($key)['relations'] ?? []));
        }

        foreach ($includes as $name) {
            array_push($paths, ...($definition->includeDefinition($name)['relations'] ?? []));
        }

        return array_values(array_unique($paths));
    }

    /**
     * For each field or include, the models its `when:` condition accepts.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $members
     * @return array<string, EloquentCollection>
     */
    protected function acceptedItems(EloquentCollection $items, string $resourceClass, array $members): array
    {
        $definition = $resourceClass::definition();
        $context = $this->context();
        $request = $this->request();
        $accepted = [];

        foreach ($members as $member) {
            $accepted[$member] = $definition->condition($member) === null
                ? $items
                : $items->filter(fn (Model $item): bool => $context->accepts($definition, $resourceClass, $member, $item, $request))->values();
        }

        return $accepted;
    }

    /**
     * Keeps only the models for which every field this member depends on exists.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function withDependencies(EloquentCollection $items, string $resourceClass, string $member): EloquentCollection
    {
        $definition = $resourceClass::definition();
        $dependencies = $definition->dependencies($member);

        if ($dependencies === []) {
            return $items;
        }

        $context = $this->context();
        $request = $this->request();

        return $items->filter(function (Model $item) use ($definition, $resourceClass, $dependencies, $context, $request): bool {
            foreach ($dependencies as $dependency) {
                if (!$context->exists($definition, $resourceClass, $dependency, $item, $request)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Loads the requested relation includes, selecting only the columns the included resource needs
     * when possible.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $includes
     * @param  array<string, EloquentCollection>  $accepted
     */
    protected function loadIncludes(string $resourceClass, SelectionNode $selection, array $fields, array $includes, array $accepted): void
    {
        $definition = $resourceClass::definition();
        $roots = array_map(fn (string $path): string => explode('.', $path)[0], $this->relationPaths($definition, $fields, $includes));
        $batches = [];

        foreach ($includes as $name) {
            if ($definition->isVirtualInclude($name)) {
                continue;
            }

            foreach ($this->modelGroups($accepted[$name]) as $group) {
                $signature = implode(',', array_map(spl_object_id(...), $group->all()));
                $batches[$signature]['models'] = $group;
                $batches[$signature]['includes'][] = $name;
            }
        }

        foreach ($batches as $batch) {
            $models = $batch['models'];
            $first = $this->blank($models->first());
            $relations = [];

            foreach ($batch['includes'] as $name) {
                $relationName = (string) $definition->includeRelation($name);
                $relation = $this->relationFor($first, $relationName, $resourceClass);
                $childClass = $definition->includeResourceClass($name);
                $childSelection = $selection->child($name);
                // a relation a field also reads is loaded whole for that field
                $whole = in_array($relationName, $roots, true);
                $columns = $childClass !== null && $childSelection !== null && !$whole && $this->isNarrowable($relation)
                    ? $this->childColumns($relation, $childClass, $childSelection)
                    : null;
                $limit = !$whole && $this->returnsList($relation) ? $definition->includeLimit($name) : null;

                $relations[$relationName] = $columns === null && $limit === null
                    ? null
                    : fn (Relation $query) => $this->constrain($query, $columns, $limit);
            }

            $models->loadMissing($this->toEagerLoadArray($relations));
        }
    }

    /**
     * Columns one level of the response reads on its models.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<int, string>
     */
    protected function nodeColumns(Model $model, string $resourceClass, SelectionNode $selection): array
    {
        if ($this->loading === 0 || $model->exists) {
            return $this->computeNodeColumns($model, $resourceClass, $selection);
        }

        $key = $resourceClass."\0".$model::class."\0".$model->getConnectionName()."\0".$model->getTable();
        $memo = $this->columnsMemo[$selection] ?? [];

        if (!isset($memo[$key])) {
            $memo[$key] = $this->computeNodeColumns($model, $resourceClass, $selection);
            $this->columnsMemo[$selection] = $memo;
        }

        return $memo[$key];
    }

    /**
     * Works out the columns to read at this level, without memory.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<int, string>
     */
    protected function computeNodeColumns(Model $model, string $resourceClass, SelectionNode $selection): array
    {
        $definition = $resourceClass::definition();
        [$fields, $includes] = $this->members($resourceClass, $selection);
        $columns = [$model->getKeyName()];
        $relationRoots = array_map(fn (string $path): string => explode('.', $path)[0], $this->relationPaths($definition, $fields, $includes));

        foreach ($fields as $key) {
            array_push($columns, ...($definition->fieldDefinition($key)['columns'] ?? []));
        }

        foreach ($includes as $name) {
            array_push($columns, ...($definition->includeDefinition($name)['columns'] ?? []));

            if (!$definition->isVirtualInclude($name)) {
                $relationRoots[] = (string) $definition->includeRelation($name);
            }
        }

        foreach (array_unique($relationRoots) as $name) {
            array_push($columns, ...$this->parentSideKeys($this->relationFor($model, $name, $resourceClass)));
        }

        return array_values(array_unique($columns));
    }

    /**
     * Fetches the needed columns that some saved models were loaded without, in one query.
     *
     * @param  array<int, string>  $columns
     */
    protected function completeColumns(EloquentCollection $models, array $columns): void
    {
        $first = $models->first();
        $keyName = $first->getKeyName();
        $incomplete = [];
        $missing = [];

        foreach ($models as $model) {
            $attributes = $model->getAttributes();
            $absent = array_diff($columns, array_keys($attributes));

            // a row without its key (an aggregate, for example) cannot be fetched again
            if ($absent !== [] && $model->exists && isset($attributes[$keyName])) {
                $incomplete[] = $model;
                array_push($missing, ...$absent);
            }
        }

        if ($incomplete === []) {
            return;
        }

        // whole rows, so a declared column that is really an alias or accessor is simply skipped
        $rows = $first->newQueryWithoutScopes()
            ->whereKey(array_map(fn (Model $model) => $model->getKey(), $incomplete))
            ->toBase()
            ->get([$first->getTable().'.*'])
            ->keyBy($keyName);

        foreach ($incomplete as $model) {
            $row = $rows->get($model->getKey());

            if ($row === null) {
                continue;
            }

            $values = array_intersect_key((array) $row, array_flip($missing));
            $values = array_diff_key($values, $model->getAttributes());
            $model->setRawAttributes([...$model->getAttributes(), ...$values]);
            $model->syncOriginalAttributes(array_keys($values));
        }
    }

    /**
     * Checks that every step of a relation path is a real relation. A path cannot go past a MorphTo,
     * because Eloquent would load the rest for the first row's type only.
     */
    protected function assertRelationPath(Model $model, string $path, string $resourceClass): void
    {
        $segments = explode('.', $path);

        foreach ($segments as $index => $segment) {
            $relation = $this->relationFor($model, $segment, $resourceClass);

            if ($index === count($segments) - 1) {
                return;
            }

            if ($relation instanceof MorphTo) {
                throw ResponseSelectionException::relationPathAfterMorphTo($resourceClass, $path, $segment);
            }

            $model = $relation->getRelated();
        }
    }

    /**
     * @return class-string<ApiResource>|null null when the child has no resource
     */
    protected function resolvedResource(ResourceResolver $resolver, Model $child): ?string
    {
        $class = $resolver->resourceFor($child);

        if ($class !== null && !in_array($class, $resolver->resources(), true)) {
            throw ResponseSelectionException::resolverResourceNotListed($resolver::class, $class);
        }

        return $class;
    }

    /**
     * Selects only the given columns and loads at most $limit children per parent, the first by key
     * when the relation sets no order.
     *
     * @param  array<int, string>|null  $columns
     */
    protected function constrain(Relation $query, ?array $columns, ?int $limit): void
    {
        $model = $query->getModel();

        if ($columns !== null) {
            $query->select(array_map($model->qualifyColumn(...), $columns));
        }

        if ($limit !== null) {
            if (empty($query->getBaseQuery()->orders)) {
                $query->orderBy($model->getQualifiedKeyName());
            }

            $query->limit($limit);
        }
    }

    /**
     * Whether a relation holds a list of children.
     */
    protected function returnsList(Relation $relation): bool
    {
        return $relation instanceof HasMany || $relation instanceof MorphMany || $relation instanceof BelongsToMany || $relation instanceof HasManyThrough;
    }

    /**
     * Columns to select when loading an included relation.
     *
     * @param  class-string<ApiResource>  $childClass
     * @return array<int, string>
     */
    protected function childColumns(Relation $relation, string $childClass, SelectionNode $childSelection): array
    {
        $columns = $this->nodeColumns($relation->getRelated(), $childClass, $childSelection);

        // the key that links each child back to its parent
        if ($relation instanceof BelongsTo) {
            $columns[] = $relation->getOwnerKeyName();
        } elseif ($relation instanceof HasOneOrMany) {
            $columns[] = $relation->getForeignKeyName();

            if ($relation instanceof MorphOneOrMany) {
                $columns[] = $relation->getMorphType();
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * Columns the parent needs for this relation to be loaded.
     *
     * @return array<int, string>
     */
    protected function parentSideKeys(Relation $relation): array
    {
        return match (true) {
            $relation instanceof MorphTo             => [$relation->getForeignKeyName(), $relation->getMorphType()],
            $relation instanceof BelongsTo           => [$relation->getForeignKeyName()],
            $relation instanceof HasOneOrManyThrough => [$relation->getLocalKeyName()],
            $relation instanceof HasOneOrMany        => [$relation->getLocalKeyName()],
            $relation instanceof BelongsToMany       => [$relation->getParentKeyName()],
            default                                  => [$relation->getParent()->getKeyName()],
        };
    }

    /**
     * Whether only some columns can be selected for this relation: simple single-table relations only.
     */
    protected function isNarrowable(Relation $relation): bool
    {
        if ($relation instanceof MorphTo || $relation instanceof BelongsToMany || $relation instanceof HasOneOrManyThrough) {
            return false;
        }

        if (!$relation instanceof BelongsTo && !$relation instanceof HasOneOrMany) {
            return false;
        }

        $base = $relation->getQuery()->getQuery();

        return $base->columns === null && $base->groups === null && $base->joins === null;
    }

    protected function relationFor(Model $model, string $name, string $resourceClass): Relation
    {
        if ($this->loading === 0 || $model->exists) {
            return $this->buildRelation($model, $name, $resourceClass);
        }

        return $this->relationsMemo[$model::class."\0".$model->getConnectionName()."\0".$model->getTable()."\0".$name]
            ??= $this->buildRelation($model, $name, $resourceClass);
    }

    /**
     * Calls the model's relation method and checks that it returns a relation.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function buildRelation(Model $model, string $name, string $resourceClass): Relation
    {
        if (!method_exists($model, $name)) {
            throw ResponseSelectionException::relationMissing($resourceClass, $name, $model::class);
        }

        $relation = $model->{$name}();

        if (!$relation instanceof Relation) {
            throw ResponseSelectionException::notARelation($resourceClass, $name, $model::class);
        }

        return $relation;
    }

    /**
     * Computes the requested computed fields, each after the fields it depends on.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $fields  in build order
     * @param  array<string, EloquentCollection>  $accepted
     */
    protected function buildFields(string $resourceClass, array $fields, array $accepted): void
    {
        $definition = $resourceClass::definition();
        $pending = array_values(array_filter($fields, fn (string $key): bool => $definition->builderOf($resourceClass, $key) !== null));

        while ($pending !== []) {
            $computedBy = $definition->fieldDefinition($pending[0])['computedBy'] ?? null;

            if ($computedBy instanceof Closure) {
                $this->computeWithClosure($this->withDependencies($accepted[$pending[0]], $resourceClass, $pending[0]), $resourceClass, $pending[0], $computedBy);
                $batch = [$pending[0]];
            } else {
                // the first pending field is ready, since build order puts it after what it needs
                $batch = array_values(array_filter($pending, fn (string $key): bool => ($definition->fieldDefinition($key)['computedBy'] ?? null) === $computedBy
                    && array_intersect($definition->dependencies($key), $pending) === []));
                $this->computeWithClass($accepted, $resourceClass, (string) $computedBy, $batch);
            }

            $pending = array_values(array_diff($pending, $batch));
        }
    }

    /**
     * Runs a FieldComputer for the models that still need its fields. Results are matched back by
     * model key, so every model must have one.
     *
     * @param  array<string, EloquentCollection>  $accepted
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $fields
     */
    protected function computeWithClass(array $accepted, string $resourceClass, string $computerClass, array $fields): void
    {
        $computer = $this->container->make($computerClass);

        if (!$computer instanceof FieldComputer) {
            throw ResponseSelectionException::classMustImplement($computerClass, FieldComputer::class);
        }

        $context = $this->context();
        $definition = $resourceClass::definition();
        $slots = [];
        $models = [];
        $keys = [];
        $pending = [];

        foreach ($fields as $field) {
            $slot = $slots[$field] = (string) $definition->slotOf($resourceClass, $field);

            foreach ($this->withDependencies($accepted[$field], $resourceClass, $field) as $item) {
                if (!$context->wasComputed($item, $slot)) {
                    $id = spl_object_id($item);

                    if (!isset($models[$id])) {
                        $keys[$id] = $this->keyOf($item, $resourceClass, $computerClass);
                        $models[$id] = $item;
                    }

                    $pending[$id][] = $field;
                }
            }
        }

        $groups = [];

        foreach ($pending as $id => $modelFields) {
            $signature = $models[$id]::class.':'.implode(',', $modelFields);
            $groups[$signature]['fields'] = $modelFields;
            $groups[$signature]['models'][$id] = $models[$id];
        }

        foreach ($groups as $group) {
            $dependencies = array_values(array_unique(array_merge(...array_map($definition->dependencies(...), $group['fields']))));
            $built = new MemberBuiltValues($context, $resourceClass, $computerClass, $dependencies);
            $values = $computer->compute(new EloquentCollection(array_values($group['models'])), $group['fields'], $built);
            $groupSlots = array_intersect_key($slots, array_flip($group['fields']));

            foreach ($group['models'] as $id => $model) {
                $context->rememberComputed($model, $groupSlots, $values[$keys[$id]] ?? []);
            }
        }
    }

    /**
     * Runs a computedBy closure on each model that still needs the field.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function computeWithClosure(EloquentCollection $items, string $resourceClass, string $key, Closure $closure): void
    {
        $context = $this->context();
        $builder = "{$resourceClass}::{$key}";
        $definition = $resourceClass::definition();
        $slot = (string) $definition->slotOf($resourceClass, $key);
        $built = new MemberBuiltValues($context, $resourceClass, $builder, $definition->dependencies($key));

        foreach ($items as $item) {
            if (!$context->wasComputed($item, $slot)) {
                $context->rememberComputedValue($item, $slot, $closure($item, $built));
            }
        }
    }

    /**
     * Loads a virtual include for the parents that do not have it yet.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function loadVirtual(EloquentCollection $parents, string $resourceClass, string $name): void
    {
        $definition = $resourceClass::definition();
        $include = $definition->includeDefinition($name);
        $loader = $include['loader'] ?? null;
        $label = $loader instanceof Closure ? "{$resourceClass}::{$name}" : (string) $loader;
        $context = $this->context();
        $pending = [];

        foreach ($parents as $parent) {
            if (!$context->hasVirtual($parent, $resourceClass, $name)) {
                $pending[] = $parent;
            }
        }

        if ($pending === []) {
            return;
        }

        $built = new MemberBuiltValues($context, $resourceClass, $label, $definition->dependencies($name));
        $asList = $definition->rendersAsList($name);

        if ($loader instanceof Closure) {
            foreach ($pending as $parent) {
                $context->rememberVirtual($parent, $resourceClass, $name, $this->virtualValue($loader($parent, $built), $asList, $resourceClass, $name));
            }

            return;
        }

        $instance = $this->container->make($label);

        if (!$instance instanceof VirtualIncludeLoader) {
            throw ResponseSelectionException::classMustImplement($label, VirtualIncludeLoader::class);
        }

        // one call per parent class, because parents of two classes may share a key
        foreach ($this->modelGroups(new EloquentCollection($pending)) as $group) {
            $keys = [];

            foreach ($group as $index => $parent) {
                $keys[$index] = $this->keyOf($parent, $resourceClass, $label);
            }

            $values = $instance->load($group, $built);

            foreach ($group as $index => $parent) {
                $context->rememberVirtual($parent, $resourceClass, $name, $this->virtualValue($values[$keys[$index]] ?? null, $asList, $resourceClass, $name));
            }
        }
    }

    /**
     * Checks what a virtual include loader returned: a model or null, or a list of models for an
     * `asList` include.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function virtualValue(mixed $value, bool $asList, string $resourceClass, string $name): Model|Collection|null
    {
        if (!$asList) {
            if ($value === null || $value instanceof Model) {
                return $value;
            }

            throw ResponseSelectionException::virtualIncludeNotModel($resourceClass, $name, get_debug_type($value));
        }

        if ($value === null) {
            return new Collection;
        }

        if (!$value instanceof Collection && (!is_array($value) || !array_is_list($value))) {
            throw ResponseSelectionException::virtualIncludeNotModel($resourceClass, $name, get_debug_type($value));
        }

        $children = collect($value)->values();

        foreach ($children as $child) {
            if (!$child instanceof Model) {
                throw ResponseSelectionException::virtualIncludeNotModel($resourceClass, $name, get_debug_type($child));
            }
        }

        return $children;
    }

    /**
     * Reads a model's key once: builders return their results by key. A model without key fails.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function keyOf(Model $model, string $resourceClass, string $builder): mixed
    {
        return $model->getKey() ?? throw ResponseSelectionException::builderModelWithoutKey($resourceClass, $builder, $model::class);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function virtualChildren(EloquentCollection $parents, string $resourceClass, string $name): EloquentCollection
    {
        $definition = $resourceClass::definition();
        $context = $this->context();
        $children = [];

        foreach ($parents as $parent) {
            if ($context->hasVirtual($parent, $resourceClass, $name)) {
                array_push($children, ...$this->includeChildren($definition, $name, $context->virtualValue($parent, $resourceClass, $name)));
            }
        }

        return new EloquentCollection($children);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function childrenOf(EloquentCollection $parents, string $resourceClass, string $name): EloquentCollection
    {
        $definition = $resourceClass::definition();
        $relation = (string) $definition->includeRelation($name);
        $children = [];

        foreach ($parents as $parent) {
            if ($parent->relationLoaded($relation)) {
                array_push($children, ...$this->includeChildren($definition, $name, $parent->getRelation($relation)));
            }
        }

        return new EloquentCollection($children);
    }

    /**
     * The child models held by an include's value.
     *
     * @return array<int, mixed>
     */
    protected function includeChildren(ResourceDefinition $definition, string $name, mixed $value): array
    {
        if ($definition->rendersChildList($name, $value)) {
            return collect($value)->values()->take($definition->includeLimit($name))->all();
        }

        return $value === null ? [] : [$value];
    }

    /**
     * @param  array<string, Closure|null>  $relations
     * @return array<int|string, string|Closure>
     */
    protected function toEagerLoadArray(array $relations): array
    {
        $eagerLoads = [];

        foreach ($relations as $path => $constraint) {
            if ($constraint === null) {
                $eagerLoads[] = $path;
            } else {
                $eagerLoads[$path] = $constraint;
            }
        }

        return $eagerLoads;
    }

    protected function unique(EloquentCollection $items): EloquentCollection
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $id = spl_object_id($item);

            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $unique[] = $item;
            }
        }

        return count($unique) === $items->count() ? $items : new EloquentCollection($unique);
    }

    protected function context(): ResponseContext
    {
        if ($this->loading === 0) {
            return $this->container->make(ResponseContext::class);
        }

        return $this->contextMemo ??= $this->container->make(ResponseContext::class);
    }

    protected function request(): Request
    {
        if ($this->loading === 0) {
            return $this->container->make('request');
        }

        return $this->requestMemo ??= $this->container->make('request');
    }
}
