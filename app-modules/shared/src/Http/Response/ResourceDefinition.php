<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Shared\Exceptions\ResponseDeclarationException;
use Lahatre\Shared\Exceptions\ResponseSelectionException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Per-Resource declaration of what can be rendered and what each piece needs, the only source of
 * its rendering: ApiResource::toArray() renders the selected members in declaration order. Every
 * name is the snake_case JSON key clients select and read.
 * - field(): a key with the root columns, Eloquent relation paths and other fields it reads, and
 *   what builds its value (a FieldComputer, a closure, or the model attribute of its key);
 * - include(): an Eloquent relation rendered by a child Resource, or by the Resource a
 *   ResourceResolver picks per model (polymorphic relation); its cardinality and the parent keys it
 *   needs come from the relation;
 * - virtualInclude(): children a VirtualIncludeLoader (or a closure) builds outside Eloquent, with
 *   the parent columns, relations and fields it reads.
 *
 * `fields:` lists other fields of this Resource a member needs: they are built before it, even when
 * not requested (built, not rendered). A member exists for a model only when every field of its
 * `fields:` exists for it: one its `when:` refuses, or its FieldComputer leaves out, takes its
 * dependents with it. `when:` decides whether a member is built and rendered:
 * `Closure(Request): bool` once per node, before anything is loaded for it;
 * `Closure(Model, Request): bool` per model, after loading and before building.
 */
final class ResourceDefinition
{
    /**
     * @var array<string, array{columns: array<int, string>, relations: array<int, string>, fields: array<int, string>, computedBy: class-string<FieldComputer>|Closure|null, when: Closure|null}>
     */
    private array $fields = [];

    /**
     * @var array<string, array{resource: class-string<ApiResource>|null, resolver: ResourceResolver|class-string<ResourceResolver>|null, relation: string|null, loader: class-string<VirtualIncludeLoader>|Closure|null, asList: bool, columns: array<int, string>, relations: array<int, string>, fields: array<int, string>, when: Closure|null}>
     */
    private array $includes = [];

    /**
     * Every field key and include name, in declaration order: the order of the rendered keys.
     *
     * @var array<int, string>
     */
    private array $members = [];

    /**
     * Field keys, each after the fields its `fields:` needs.
     *
     * @var array<int, string>|null
     */
    private ?array $buildOrder = null;

    /**
     * Whether the `when:` of a member reads the request only, per member declaring one.
     *
     * @var array<string, bool>|null
     */
    private ?array $requestOnly = null;

    /**
     * Names declared a second time (parent::define() included), reported by freeze() with the Resource.
     *
     * @var array<int, string>
     */
    private array $declaredTwice = [];

    private bool $frozen = false;

    /**
     * Called by ApiResource::definition() once define() has run: the definition is then shared by every
     * render of its Resource, so a later change would alter what every other render relies on. Checks
     * that no member is declared twice and the `fields:` dependencies, fixes the build order and reads
     * the shape of every `when:`.
     *
     * @param  class-string<ApiResource>  $resourceClass  the Resource it defines, for error messages
     */
    public function freeze(string $resourceClass): self
    {
        if ($this->declaredTwice !== []) {
            throw ResponseSelectionException::memberDeclaredTwice($resourceClass, $this->declaredTwice[0]);
        }

        $this->buildOrder = $this->sortFields($resourceClass);
        $this->requestOnly = $this->conditionShapes($resourceClass);
        $this->frozen = true;

        return $this;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw ResponseSelectionException::definitionFrozen();
        }
    }

    /**
     * A field declared bare reads the column of its name. Declaring any of `columns`, `relations`,
     * `fields` or `computedBy` lists everything it reads instead: `columns: []` alone reads nothing (a
     * constant, the session, a setting).
     *
     * Its value comes from `computedBy` (a class: ResponseContext; a closure: its result), otherwise
     * from getAttribute($name) on the model (column, cast or accessor).
     *
     * @param  string  $name  JSON key of the field, as clients select it
     * @param  array<int, string>|null  $columns  root-table columns this field reads
     * @param  array<int, string>|null  $relations  Eloquent relation paths this field reads (relative to the model), loaded
     *                                              with every column
     * @param  array<int, string>  $fields  other fields of this Resource the field needs, built before it
     * @param  class-string<FieldComputer>|(Closure(Model, BuiltValues): mixed)|null  $computedBy  a
     *                                                                                             FieldComputer computes every model of the node at once; a closure computes one model and must not query
     * @param  (Closure(Request): bool)|(Closure(Model, Request): bool)|null  $when  visibility: the field is
     *                                                                               neither built nor rendered where it does not return true (see the class docblock)
     */
    public function field(string $name, ?array $columns = null, ?array $relations = null, array $fields = [], string|Closure|null $computedBy = null, ?Closure $when = null): self
    {
        $this->assertMutable();
        $this->assertIdentifier($name);

        if ($this->declares($name)) {
            $this->declaredTwice[] = $name;

            return $this;
        }

        if ($columns === null && $relations === null && $fields === [] && $computedBy === null) {
            $columns = [$name];
        }

        $this->fields[$name] = [
            'columns'    => array_values($columns ?? []),
            'relations'  => array_values(array_unique($relations ?? [])),
            'fields'     => array_values(array_unique($fields)),
            'computedBy' => $computedBy,
            'when'       => $when,
        ];
        $this->remember($name);

        return $this;
    }

    /**
     * An Eloquent relation rendered by a child Resource. For a polymorphic relation, pass the
     * ResourceResolver that picks the Resource of each child model instead of a Resource. A filtered
     * relation is a dedicated relation of the model, named by `relation`.
     *
     * @param  string  $name  JSON key of the include, as clients select it
     * @param  class-string<ApiResource>|ResourceResolver|class-string<ResourceResolver>  $resource
     * @param  string|null  $relation  Eloquent relation the include loads; the camelCase name by default
     * @param  (Closure(Request): bool)|(Closure(Model, Request): bool)|null  $when  visibility: the relation is
     *                                                                               neither loaded nor rendered where it does not return true
     */
    public function include(string $name, string|ResourceResolver $resource, ?string $relation = null, ?Closure $when = null): self
    {
        [$resourceClass, $resolver] = $this->splitResource($resource);

        return $this->addInclude($name, $resourceClass, $resolver, $relation ?? Str::camel($name), null, false, [], [], [], $when);
    }

    /**
     * Children built outside Eloquent, kept in ResponseContext, never set on the parent model.
     *
     * @param  string  $name  JSON key of the include, as clients select it
     * @param  class-string<ApiResource>|ResourceResolver|class-string<ResourceResolver>  $resource  a resolver needs model children
     * @param  class-string<VirtualIncludeLoader>|(Closure(Model, BuiltValues): mixed)  $loader  a
     *                                                                                           VirtualIncludeLoader loads every parent at once; a closure builds the children of one parent and must not query
     * @param  bool  $asList  renders a list (an empty one for a parent without children) instead of one child or null
     * @param  array<int, string>  $columns  parent-table columns the loader reads
     * @param  array<int, string>  $relations  parent relation paths the loader reads, loaded with every column
     * @param  array<int, string>  $fields  parent fields the loader needs, built before it
     * @param  (Closure(Request): bool)|(Closure(Model, Request): bool)|null  $when  visibility: refused parents
     *                                                                               are not passed to the loader and render no key
     */
    public function virtualInclude(string $name, string|ResourceResolver $resource, string|Closure $loader, bool $asList = false, array $columns = [], array $relations = [], array $fields = [], ?Closure $when = null): self
    {
        [$resourceClass, $resolver] = $this->splitResource($resource);

        return $this->addInclude($name, $resourceClass, $resolver, null, $loader, $asList, $columns, $relations, $fields, $when);
    }

    /**
     * @param  class-string<ApiResource>|ResourceResolver|class-string<ResourceResolver>  $resource
     * @return array{0: class-string<ApiResource>|null, 1: ResourceResolver|class-string<ResourceResolver>|null}
     */
    private function splitResource(string|ResourceResolver $resource): array
    {
        if ($resource instanceof ResourceResolver || is_subclass_of($resource, ResourceResolver::class)) {
            return [null, $resource];
        }

        return [$resource, null];
    }

    /**
     * @param  class-string<ApiResource>|null  $resource
     * @param  ResourceResolver|class-string<ResourceResolver>|null  $resolver
     * @param  string|null  $relation  null for a virtual include
     * @param  class-string<VirtualIncludeLoader>|Closure|null  $loader
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     * @param  array<int, string>  $fields
     */
    private function addInclude(string $name, ?string $resource, string|ResourceResolver|null $resolver, ?string $relation, string|Closure|null $loader, bool $asList, array $columns, array $relations, array $fields, ?Closure $when): self
    {
        $this->assertMutable();
        $this->assertIdentifier($name);

        if ($this->declares($name)) {
            $this->declaredTwice[] = $name;

            return $this;
        }

        if ($relation !== null) {
            $existing = $this->includeForRelation($relation);

            if ($existing !== null && $existing !== $name) {
                throw ResponseSelectionException::relationIncludedTwice($relation, $existing, $name);
            }
        }

        $this->includes[$name] = [
            'resource'  => $resource,
            'resolver'  => $resolver,
            'relation'  => $relation,
            'loader'    => $loader,
            'asList'    => $asList,
            'columns'   => array_values($columns),
            'relations' => array_values(array_unique($relations)),
            'fields'    => array_values(array_unique($fields)),
            'when'      => $when,
        ];
        $this->remember($name);

        return $this;
    }

    /**
     * A member is declared once: a child Resource adds to parent::define(), it never replaces.
     */
    private function declares(string $name): bool
    {
        return isset($this->fields[$name]) || isset($this->includes[$name]);
    }

    private function remember(string $name): void
    {
        if (!in_array($name, $this->members, true)) {
            $this->members[] = $name;
        }
    }

    /**
     * JSON keys are snake_case, a subset of the `fields` / `include` selection grammar.
     */
    private function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $name)) {
            throw ResponseSelectionException::invalidName($name);
        }
    }

    /**
     * Field keys in build order: depth first over `fields:`, in declaration order. Rejects a key
     * `fields:` names that is not a field, a field needing itself, and cycles.
     *
     * @param  string  $resourceClass  for error messages, empty before freeze()
     * @return array<int, string>
     */
    private function sortFields(string $resourceClass): array
    {
        foreach ($this->includes as $name => $include) {
            foreach ($include['fields'] as $field) {
                if (!isset($this->fields[$field])) {
                    throw ResponseSelectionException::unknownFieldDependency($resourceClass, $name, $field);
                }
            }
        }

        $order = [];
        $state = [];

        foreach (array_keys($this->fields) as $key) {
            $this->visitField($resourceClass, $key, [], $state, $order);
        }

        return $order;
    }

    /**
     * @param  array<int, string>  $path  fields being visited, outermost first
     * @param  array<string, bool>  $state  false while a field is being visited, true once sorted
     * @param  array<int, string>  $order
     */
    private function visitField(string $resourceClass, string $key, array $path, array &$state, array &$order): void
    {
        if (($state[$key] ?? null) === true) {
            return;
        }

        if (($state[$key] ?? null) === false) {
            $start = (int) array_search($key, $path, true);

            throw ResponseSelectionException::fieldDependencyCycle($resourceClass, [...array_slice($path, $start), $key]);
        }

        $state[$key] = false;

        foreach ($this->fields[$key]['fields'] as $dependency) {
            if ($dependency === $key) {
                throw ResponseSelectionException::fieldDependsOnItself($resourceClass, $key);
            }

            if (!isset($this->fields[$dependency])) {
                throw ResponseSelectionException::unknownFieldDependency($resourceClass, $key, $dependency);
            }

            $this->visitField($resourceClass, $dependency, [...$path, $key], $state, $order);
        }

        $state[$key] = true;
        $order[] = $key;
    }

    /**
     * Every field key and include name, in declaration order.
     *
     * @return array<int, string>
     */
    public function members(): array
    {
        return $this->members;
    }

    /**
     * Field keys in build order: every field comes after the fields its `fields:` lists.
     *
     * @return array<int, string>
     */
    public function buildOrder(): array
    {
        return $this->buildOrder ??= $this->sortFields('');
    }

    /**
     * The fields to build for these members: the members that are fields, plus every field their
     * `fields:` need, transitively, in build order. A member $accepts refuses is skipped with the
     * dependencies only it needs; $accepts refuses the members depending on a refused one too.
     *
     * @param  array<int, string>  $members  field keys and include names
     * @param  (Closure(string): bool)|null  $accepts
     * @return array<int, string>
     */
    public function fieldsToBuild(array $members, ?Closure $accepts = null): array
    {
        $needed = [];
        $pending = $members;
        $seen = [];

        while ($pending !== []) {
            $name = array_pop($pending);

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            if ($accepts !== null && !$accepts($name)) {
                continue;
            }

            if (isset($this->fields[$name])) {
                $needed[$name] = true;
            }

            array_push($pending, ...$this->dependencies($name));
        }

        return array_values(array_filter($this->buildOrder(), fn (string $key) => isset($needed[$key])));
    }

    /**
     * The `fields:` of a field or include.
     *
     * @return array<int, string>
     */
    public function dependencies(string $name): array
    {
        return $this->fields[$name]['fields'] ?? $this->includes[$name]['fields'] ?? [];
    }

    public function hasField(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    /**
     * @return array<int, string>
     */
    public function fieldKeys(): array
    {
        return array_keys($this->fields);
    }

    /**
     * @return array{columns: array<int, string>, relations: array<int, string>, fields: array<int, string>, computedBy: class-string<FieldComputer>|Closure|null, when: Closure|null}|null
     */
    public function fieldDefinition(string $key): ?array
    {
        return $this->fields[$key] ?? null;
    }

    /**
     * Where the computed values of a field are kept in ResponseContext: its FieldComputer class, or
     * `Resource::key` for a closure; null when the field is not computed.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function builderOf(string $resourceClass, string $key): ?string
    {
        $computedBy = $this->fields[$key]['computedBy'] ?? null;

        return $computedBy instanceof Closure ? "{$resourceClass}::{$key}" : $computedBy;
    }

    /**
     * The `when:` of a field or include.
     */
    public function condition(string $name): ?Closure
    {
        return $this->fields[$name]['when'] ?? $this->includes[$name]['when'] ?? null;
    }

    /**
     * Whether the `when:` of a member is a `Closure(Request): bool`, evaluated once per node before
     * loading; false for a per-model `when:` or none.
     */
    public function conditionReadsRequestOnly(string $name): bool
    {
        return ($this->requestOnly ??= $this->conditionShapes(null))[$name] ?? false;
    }

    /**
     * @param  class-string<ApiResource>|null  $resourceClass
     * @return array<string, bool>
     */
    private function conditionShapes(?string $resourceClass): array
    {
        $shapes = [];

        foreach ([...$this->fields, ...$this->includes] as $name => $member) {
            if ($member['when'] !== null) {
                $shapes[$name] = $this->readsRequestOnly($member['when'], $resourceClass ?? '', $name);
            }
        }

        return $shapes;
    }

    /**
     * `Closure(Request $request)`: one parameter typed as the Request. `Closure($model, $request)`:
     * two parameters, the first not typed as the Request, the second untyped or typed as it. The
     * runtime passes app('request'), a plain Request: a subclass (FormRequest) is not accepted.
     */
    private function readsRequestOnly(Closure $when, string $resourceClass, string $member): bool
    {
        $parameters = (new ReflectionFunction($when))->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                throw ResponseDeclarationException::invalidCondition($resourceClass, $member);
            }
        }

        if (count($parameters) === 1 && $this->typedAsRequest($parameters[0])) {
            return true;
        }

        if (count($parameters) === 2 && !$this->typedAsRequest($parameters[0])
            && ($parameters[1]->getType() === null || $this->typedAsRequest($parameters[1]))) {
            return false;
        }

        throw ResponseDeclarationException::invalidCondition($resourceClass, $member);
    }

    private function typedAsRequest(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin() && is_a(Request::class, $type->getName(), true);
    }

    /**
     * @return array<int, string>
     */
    public function includeNames(): array
    {
        return array_keys($this->includes);
    }

    /**
     * @return array{resource: class-string<ApiResource>|null, resolver: ResourceResolver|class-string<ResourceResolver>|null, relation: string|null, loader: class-string<VirtualIncludeLoader>|Closure|null, asList: bool, columns: array<int, string>, relations: array<int, string>, fields: array<int, string>, when: Closure|null}|null
     */
    public function includeDefinition(string $name): ?array
    {
        return $this->includes[$name] ?? null;
    }

    /**
     * The relation an Eloquent include loads, or null for a virtual include or an unknown name.
     */
    public function includeRelation(string $name): ?string
    {
        return $this->includes[$name]['relation'] ?? null;
    }

    /**
     * The include that loads a relation, or null when no include loads it.
     */
    public function includeForRelation(string $relation): ?string
    {
        foreach ($this->includes as $name => $include) {
            if ($include['relation'] === $relation) {
                return $name;
            }
        }

        return null;
    }

    public function isVirtualInclude(string $name): bool
    {
        return ($this->includes[$name]['loader'] ?? null) !== null;
    }

    /**
     * The fixed child Resource class of an include, or null when a resolver picks it per model.
     *
     * @return class-string<ApiResource>|null
     */
    public function includeResourceClass(string $name): ?string
    {
        return $this->includes[$name]['resource'] ?? null;
    }

    public function includeResolver(string $name): ?ResourceResolver
    {
        $resolver = $this->includes[$name]['resolver'] ?? null;

        return is_string($resolver) ? app($resolver) : $resolver;
    }

    /**
     * Whether a virtual include renders a list rather than one child.
     */
    public function rendersAsList(string $name): bool
    {
        return $this->includes[$name]['asList'] ?? false;
    }

    /**
     * Whether an include value renders a list of children: a virtual include declared `asList`, an
     * Eloquent include whose relation holds a collection. Any other value is one child, or null.
     */
    public function rendersChildList(string $name, mixed $value): bool
    {
        return $this->isVirtualInclude($name) ? $this->rendersAsList($name) : $value instanceof Collection;
    }

    /**
     * Every child Resource class an include may render.
     *
     * @return array<int, class-string<ApiResource>>
     */
    public function includeResourceClasses(string $name): array
    {
        $class = $this->includeResourceClass($name);
        if ($class !== null) {
            return [$class];
        }

        return $this->includeResolver($name)?->resources() ?? [];
    }
}
