<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use WeakMap;

/**
 * Loads and builds everything a selection needs to render, node by node, after the business query.
 *
 * Per node: request-only `when:` drop the members they refuse, the members whose `fields:` need a
 * refused one, and the fields only those need; then, per model class, one query completes the columns of the members to build when some models
 * lack them and one loadMissing() loads their relation paths; per-model `when:` then pick the
 * models each member is built for; one loadMissing() per set of accepted models loads the selected
 * Eloquent includes (narrowed to the child's columns + relation keys); computed fields are built in
 * `fields:` order (each FieldComputer once per model class and set of pending fields, usually
 * once), each for the models every member of its `fields:` exists for; virtual includes are loaded
 * the same way; then the loader recurses into each include over the children of all
 * accepted parents. Queries are therefore bounded by the number of nodes, never by the number of
 * rows. Built values and virtual children are kept in ResponseContext, never on the models.
 *
 * The engine handles Eloquent models only. Every model a node reaches through loaded relations, and
 * every virtual child, has lazy loading prevented until release(): a relation read without being
 * loaded throws instead of querying, while building and rendering. Eloquent exempts a model that is
 * not persisted or was created in this request (`!exists || wasRecentlyCreated`): it still lazy-loads,
 * so the relations such a model needs are declared in `relations:` or loaded beforehand.
 */
class SelectionLoader
{
    /**
     * Lazy-loading flag each reached model had before load(), restored by release().
     *
     * @var WeakMap<Model, bool>
     */
    protected WeakMap $lazyLoading;

    public function __construct(protected Container $container)
    {
        $this->lazyLoading = new WeakMap;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function load(Model|EloquentCollection $result, string $resourceClass, SelectionNode $selection): void
    {
        $this->loadNode($result instanceof Model ? new EloquentCollection([$result]) : $result, $resourceClass, $selection);
    }

    /**
     * Gives back to every model load() reached the lazy-loading flag it had, once rendered.
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

        // protected before the per-model `when:` read what was loaded
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

                // a child without API representation renders null: nothing is loaded under it
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
     * What a node builds: the fields to build (requested ones and those `fields:` need, in build
     * order) and the selected includes, both without the members that do not exist for the request:
     * refused by a request-only `when:`, or needing such a member through `fields:`.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected function members(string $resourceClass, SelectionNode $selection): array
    {
        $definition = $resourceClass::definition();
        $context = $this->context();
        $request = $this->request();
        $accepts = fn (string $name): bool => $context->existsForRequest($definition, $resourceClass, $name, $request);

        $includes = array_values(array_filter(
            $definition->includeNames(),
            fn (string $name) => $selection->hasChild($name) && $accepts($name)
        ));
        $requested = array_values(array_filter(
            $definition->fieldKeys(),
            fn (string $key) => $key === 'id' || $selection->wantsField($key)
        ));

        return [$definition->fieldsToBuild([...$requested, ...$includes], $accepts), $includes];
    }

    /**
     * Eloquent models of a node, grouped by class: Eloquent builds an eager load from the first model
     * of a collection, so each model class of a mixed node (polymorphic children, resolver groups)
     * loads with its own relations.
     *
     * @return array<int, EloquentCollection>
     */
    protected function modelGroups(EloquentCollection $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $groups[$item::class][] = $item;
        }

        return array_values(array_map(fn (array $models) => new EloquentCollection($models), $groups));
    }

    /**
     * Prevents lazy loading on these models and on every model reached through their loaded
     * relations, recursively: Eloquent sets the flag only on the rows of a query returning several,
     * and getRelationValue() reads the model's own flag. The first flag seen is kept for release().
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
     * Loads what the members of a node read on models of one class, before any `when:` runs per
     * model: the columns they lack, then the relation paths of the fields to build and the virtual
     * includes. Checks every relation the node uses.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $includes
     */
    protected function loadDependencies(EloquentCollection $models, string $resourceClass, SelectionNode $selection, array $fields, array $includes): void
    {
        $definition = $resourceClass::definition();
        // relations are introspected on a blank instance: building one reads the parent key, which
        // a model that still lacks columns cannot provide before completeColumns()
        $first = $models->first()->newInstance();
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
     * Relation paths the fields to build and the virtual includes of a node read.
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
     * The items each member is built for: those its per-model `when:` accepts (all of them without
     * one).
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
                : $items->filter(fn ($item) => $context->accepts($definition, $resourceClass, $member, $item, $request))->values();
        }

        return $accepted;
    }

    /**
     * The items a member is built for among those its `when:` accepts: the ones every member of its
     * `fields:` exists for, those being built already.
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
     * Loads the selected Eloquent includes on the models that accept them: one loadMissing() per
     * model class and set of accepted models. An include is narrowed to the child's columns unless
     * it is the root of a relation path the node reads, which loadDependencies() loaded whole.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $includes
     * @param  array<string, EloquentCollection>  $accepted
     */
    protected function loadIncludes(string $resourceClass, SelectionNode $selection, array $fields, array $includes, array $accepted): void
    {
        $definition = $resourceClass::definition();
        $roots = array_map(fn (string $path) => explode('.', $path)[0], $this->relationPaths($definition, $fields, $includes));
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
            $first = $models->first()->newInstance();
            $relations = [];

            foreach ($batch['includes'] as $name) {
                $relationName = (string) $definition->includeRelation($name);
                $relation = $this->relationFor($first, $relationName, $resourceClass);
                $childClass = $definition->includeResourceClass($name);
                $childSelection = $selection->child($name);

                if ($childClass !== null && $childSelection !== null && !in_array($relationName, $roots, true) && $this->isNarrowable($relation)) {
                    $columns = $this->childColumns($relation, $childClass, $childSelection);
                    $relations[$relationName] = fn ($query) => $this->narrow($query, $columns);
                } else {
                    $relations[$relationName] = null;
                }
            }

            $models->loadMissing($this->toEagerLoadArray($relations));
        }
    }

    /**
     * Columns a node reads on its own models: the key, the columns of the fields to build and of
     * the selected includes, and the keys of every relation the node loads.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<int, string>
     */
    protected function nodeColumns(Model $model, string $resourceClass, SelectionNode $selection): array
    {
        $definition = $resourceClass::definition();
        [$fields, $includes] = $this->members($resourceClass, $selection);
        $columns = [$model->getKeyName()];
        $relationRoots = array_map(fn (string $path) => explode('.', $path)[0], $this->relationPaths($definition, $fields, $includes));

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
     * Fills, in place, the columns a node needs that some of its persisted models lack: a model
     * shared by several nodes (or narrowed by business code) was loaded with another column set.
     * One query per node, only when something is missing; values are synced as original.
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

            // aggregate rows without their key cannot be completed
            if ($absent !== [] && $model->exists && isset($attributes[$keyName])) {
                $incomplete[] = $model;
                array_push($missing, ...$absent);
            }
        }

        if ($incomplete === []) {
            return;
        }

        // whole rows: an alias or accessor name declared as a column is simply not found
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
     * Each segment of a relation path must be a relation of the previous model. A path cannot continue
     * after a MorphTo: Eloquent would resolve the nested relations on the first row's type only.
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
     * @return class-string<ApiResource>|null null when the child has no API representation
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
     * @param  array<int, string>  $columns
     */
    protected function narrow(Relation $query, array $columns): void
    {
        $model = $query->getModel();
        $query->select(array_map(fn (string $column) => $model->qualifyColumn($column), $columns));
    }

    /**
     * Columns to select on an included relation: the child's selected field columns, its primary
     * key, the key the relation matches on, and the keys its own selected includes and field relations need.
     *
     * @param  class-string<ApiResource>  $childClass
     * @return array<int, string>
     */
    protected function childColumns(Relation $relation, string $childClass, SelectionNode $childSelection): array
    {
        $columns = $this->nodeColumns($relation->getRelated(), $childClass, $childSelection);

        // key the parent relation matches on, on the child side
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
     * Columns the parent model must carry for a relation to be eager-loaded and matched.
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
     * Only single-table relations without their own select/grouping are narrowed.
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
     * Builds the computed fields of a node in build order: a closure per accepted model, a
     * FieldComputer once for all its fields whose `fields:` are built, over the models that accept
     * them and for which every member of their `fields:` exists.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $fields  fields to build, in build order
     * @param  array<string, EloquentCollection>  $accepted
     */
    protected function buildFields(string $resourceClass, array $fields, array $accepted): void
    {
        $definition = $resourceClass::definition();
        $pending = array_values(array_filter($fields, fn (string $key) => $definition->builderOf($resourceClass, $key) !== null));

        while ($pending !== []) {
            $computedBy = $definition->fieldDefinition($pending[0])['computedBy'] ?? null;

            if ($computedBy instanceof Closure) {
                $this->computeWithClosure($this->withDependencies($accepted[$pending[0]], $resourceClass, $pending[0]), $resourceClass, $pending[0], $computedBy);
                $batch = [$pending[0]];
            } else {
                // build order puts the first pending field after everything it needs
                $batch = array_values(array_filter($pending, fn (string $key) => ($definition->fieldDefinition($key)['computedBy'] ?? null) === $computedBy
                    && array_intersect($definition->dependencies($key), $pending) === []));
                $this->computeWithClass($accepted, $resourceClass, (string) $computedBy, $batch);
            }

            $pending = array_values(array_diff($pending, $batch));
        }
    }

    /**
     * Runs a FieldComputer on the fields each model accepts and has not computed yet in this
     * response, one call per model class and distinct set of pending fields, and records the values
     * in ResponseContext: they are matched back by model key, which a model must therefore have.
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
        $models = [];
        $pending = [];

        foreach ($fields as $field) {
            foreach ($this->withDependencies($accepted[$field], $resourceClass, $field) as $item) {
                if (!$context->wasComputed($item, $computerClass, $field)) {
                    $this->assertKeyed($item, $resourceClass, $computerClass);
                    $models[spl_object_id($item)] = $item;
                    $pending[spl_object_id($item)][] = $field;
                }
            }
        }

        $groups = [];

        foreach ($pending as $id => $modelFields) {
            $signature = $models[$id]::class.':'.implode(',', $modelFields);
            $groups[$signature]['fields'] = $modelFields;
            $groups[$signature]['models'][] = $models[$id];
        }

        $definition = $resourceClass::definition();

        foreach ($groups as $group) {
            $dependencies = array_values(array_unique(array_merge(...array_map($definition->dependencies(...), $group['fields']))));
            $built = new MemberBuiltValues($context, $resourceClass, $computerClass, $dependencies);
            $values = $computer->compute(new EloquentCollection($group['models']), $group['fields'], $built);

            foreach ($group['models'] as $model) {
                $context->rememberComputed($model, $computerClass, $group['fields'], $values[$model->getKey()] ?? []);
            }
        }
    }

    /**
     * Runs a computedBy closure on each accepted model that lacks the field in this response.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function computeWithClosure(EloquentCollection $items, string $resourceClass, string $key, Closure $closure): void
    {
        $context = $this->context();
        $builder = "{$resourceClass}::{$key}";
        $built = new MemberBuiltValues($context, $resourceClass, $builder, $resourceClass::definition()->dependencies($key));

        foreach ($items as $item) {
            if (!$context->wasComputed($item, $builder, $key)) {
                $context->rememberComputed($item, $builder, [$key], [$key => $closure($item, $built)]);
            }
        }
    }

    /**
     * Loads a virtual include for the accepted parents it was not loaded for in this response and
     * keeps, per parent, its value or no children (null, or an empty list for an `asList` include).
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

        // one call per parent class: parents of two classes may share a key
        foreach ($this->modelGroups(new EloquentCollection($pending)) as $group) {
            $group->each(fn (Model $parent) => $this->assertKeyed($parent, $resourceClass, $label));
            $values = $instance->load($group, $built);

            foreach ($group as $parent) {
                $context->rememberVirtual($parent, $resourceClass, $name, $this->virtualValue($values[$parent->getKey()] ?? null, $asList, $resourceClass, $name));
            }
        }
    }

    /**
     * The children a virtual include keeps for a parent: a model or null, or for an `asList`
     * include a list of models (empty for null).
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

        if (!$value instanceof Collection && !(is_array($value) && array_is_list($value))) {
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
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function assertKeyed(Model $model, string $resourceClass, string $builder): void
    {
        if ($model->getKey() === null) {
            throw ResponseSelectionException::builderModelWithoutKey($resourceClass, $builder, $model::class);
        }
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
     * The children an include value holds, classified as rendering does.
     *
     * @return array<int, mixed>
     */
    protected function includeChildren(ResourceDefinition $definition, string $name, mixed $value): array
    {
        if ($definition->rendersChildList($name, $value)) {
            return collect($value)->values()->all();
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
        return $this->container->make(ResponseContext::class);
    }

    protected function request(): Request
    {
        return $this->container->make('request');
    }
}
