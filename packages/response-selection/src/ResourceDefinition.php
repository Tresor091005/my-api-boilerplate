<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\ResponseSelection\Exceptions\ResponseDeclarationException;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * What a Resource can render, declared in its define(): fields, Eloquent relations (include()) and
 * children built in code (virtualInclude()). Each name is the snake_case JSON key clients ask for.
 */
final class ResourceDefinition
{
    /**
     * @var array<string, array{columns: array<int, string>, relations: array<int, string>, fields: array<int, string>, computedBy: class-string<FieldComputer>|Closure|null, when: Closure|null}>
     */
    private array $fields = [];

    /**
     * @var array<string, array{resource: class-string<ApiResource>|null, resolver: ResourceResolver|class-string<ResourceResolver>|null, relation: string|null, loader: class-string<VirtualIncludeLoader>|Closure|null, asList: bool, columns: array<int, string>, relations: array<int, string>, fields: array<int, string>, limit: int|null, when: Closure|null}>
     */
    private array $includes = [];

    /**
     * Field and include names in declaration order, which is the order they render in.
     *
     * @var array<int, string>
     */
    private array $members = [];

    /**
     * Field names, each after the fields it needs.
     *
     * @var array<int, string>|null
     */
    private ?array $buildOrder = null;

    /**
     * For each `when:`, whether it only looks at the request.
     *
     * @var array<string, bool>|null
     */
    private ?array $requestOnly = null;

    /**
     * Names declared twice, reported by freeze().
     *
     * @var array<int, string>
     */
    private array $declaredTwice = [];

    private bool $frozen = false;

    /**
     * The Resource class freeze() was given, whose slots below are precomputed.
     *
     * @var class-string<ApiResource>|null
     */
    private ?string $owner = null;

    /**
     * Slot of each computed field, as slotOf() names it for $owner.
     *
     * @var array<string, string>
     */
    private array $slots = [];

    /**
     * What computes each computed field, as builderOf() names it for $owner.
     *
     * @var array<string, string>
     */
    private array $builders = [];

    /**
     * The `when:` of each member that has one, once frozen.
     *
     * @var array<string, Closure>
     */
    private array $conditions = [];

    /**
     * The `fields:` of each member, once frozen.
     *
     * @var array<string, array<int, string>>
     */
    private array $needs = [];

    /**
     * `Resource::member` of each member, the key of its `when:` result in ResponseContext.
     *
     * @var array<string, string>
     */
    private array $memberKeys = [];

    /**
     * The include loading each relation.
     *
     * @var array<string, string>
     */
    private array $includeByRelation = [];

    /**
     * existenceSteps() of the members asked so far, for $owner.
     *
     * @var array<string, array<int, array{0: string, 1: Closure|null, 2: bool, 3: string|null}>>
     */
    private array $existenceSteps = [];

    /**
     * Checks the definition for mistakes and locks it, since every render of the Resource shares it.
     *
     * @param  class-string<ApiResource>  $resourceClass  used in error messages
     */
    public function freeze(string $resourceClass): self
    {
        if ($this->declaredTwice !== []) {
            throw ResponseSelectionException::memberDeclaredTwice($resourceClass, $this->declaredTwice[0]);
        }

        $this->resolveLimits($resourceClass);
        $this->buildOrder = $this->sortFields($resourceClass);
        $this->requestOnly = $this->conditionShapes($resourceClass);
        $this->owner = $resourceClass;

        foreach ($this->fields as $key => $field) {
            $this->needs[$key] = $field['fields'];

            if ($field['when'] !== null) {
                $this->conditions[$key] = $field['when'];
            }

            if ($field['computedBy'] !== null) {
                $this->slots[$key] = $this->slotFor($resourceClass, $key, $field['computedBy']);
                $this->builders[$key] = $field['computedBy'] instanceof Closure ? "{$resourceClass}::{$key}" : $field['computedBy'];
            }
        }

        foreach ($this->includes as $name => $include) {
            $this->needs[$name] = $include['fields'];

            if ($include['when'] !== null) {
                $this->conditions[$name] = $include['when'];
            }
        }

        foreach ($this->members as $name) {
            $this->memberKeys[$name] = "{$resourceClass}::{$name}";
        }

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
     * Declares a field. Alone, it renders the model attribute of the same name. Otherwise list all it
     * reads (`columns: []` for none), and `computedBy` gives its value: a FieldComputer for all
     * models at once, or a closure for one model that must not query.
     *
     * @param  string  $name  JSON key clients ask for
     * @param  array<int, string>|null  $columns  columns of the model's table it reads
     * @param  array<int, string>|null  $relations  relations it reads, loaded with all their columns
     * @param  array<int, string>  $fields  other fields of this Resource it needs
     * @param  class-string<FieldComputer>|(Closure(Model, BuiltValues): mixed)|null  $computedBy
     * @param  (Closure(Request): bool)|(Closure(Model, Request): bool)|null  $when  rendered only when true
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
     * Declares an Eloquent relation rendered by another Resource. For a polymorphic relation, pass
     * a ResourceResolver instead of a Resource.
     *
     * @param  string  $name  JSON key clients ask for
     * @param  class-string<ApiResource>|ResourceResolver|class-string<ResourceResolver>  $resource
     * @param  string|null  $relation  model relation to load; by default the camelCase of $name
     * @param  int|null  $limit  most children rendered per parent for a list; by default the configured maximum
     * @param  (Closure(Request): bool)|(Closure(Model, Request): bool)|null  $when  rendered only when true
     */
    public function include(string $name, string|ResourceResolver $resource, ?string $relation = null, ?int $limit = null, ?Closure $when = null): self
    {
        [$resourceClass, $resolver] = $this->splitResource($resource);

        return $this->addInclude($name, $resourceClass, $resolver, $relation ?? Str::camel($name), null, false, [], [], [], $limit, $when);
    }

    /**
     * Declares children built in code instead of an Eloquent relation. `loader` builds them: a
     * VirtualIncludeLoader for all parents at once, or a closure for one parent that must not query.
     *
     * @param  string  $name  JSON key clients ask for
     * @param  class-string<ApiResource>|ResourceResolver|class-string<ResourceResolver>  $resource
     * @param  class-string<VirtualIncludeLoader>|(Closure(Model, BuiltValues): mixed)  $loader
     * @param  bool  $asList  render a list (maybe empty) instead of one child or null
     * @param  array<int, string>  $columns  parent columns the loader reads
     * @param  array<int, string>  $relations  parent relations the loader reads
     * @param  array<int, string>  $fields  parent fields the loader needs
     * @param  int|null  $limit  most children rendered per parent for a list; by default the configured maximum
     * @param  (Closure(Request): bool)|(Closure(Model, Request): bool)|null  $when  rendered only when true
     */
    public function virtualInclude(string $name, string|ResourceResolver $resource, string|Closure $loader, bool $asList = false, array $columns = [], array $relations = [], array $fields = [], ?int $limit = null, ?Closure $when = null): self
    {
        [$resourceClass, $resolver] = $this->splitResource($resource);

        return $this->addInclude($name, $resourceClass, $resolver, null, $loader, $asList, $columns, $relations, $fields, $limit, $when);
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
    private function addInclude(string $name, ?string $resource, string|ResourceResolver|null $resolver, ?string $relation, string|Closure|null $loader, bool $asList, array $columns, array $relations, array $fields, ?int $limit, ?Closure $when): self
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

            $this->includeByRelation[$relation] ??= $name;
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
            'limit'     => $limit,
            'when'      => $when,
        ];
        $this->remember($name);

        return $this;
    }

    /**
     * Gives every include its limit: its own, or the configured maximum it may not exceed.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    private function resolveLimits(string $resourceClass): void
    {
        $max = (int) config('response-selection.limits.max_include_items');

        foreach ($this->includes as $name => $include) {
            $limit = $include['limit'] ?? $max;

            if ($limit < 1 || $limit > $max) {
                throw ResponseSelectionException::includeLimitInvalid($resourceClass, $name, $limit, $max);
            }

            $this->includes[$name]['limit'] = $limit;
        }
    }

    /**
     * A name is declared only once, even by a Resource extending another.
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
     * Names are snake_case so clients can select them.
     */
    private function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $name)) {
            throw ResponseSelectionException::invalidName($name);
        }
    }

    /**
     * Orders the fields so each comes after the fields it needs, and rejects unknown or circular needs.
     *
     * @param  string  $resourceClass  used in error messages
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
     * Field and include names, in declaration order.
     *
     * @return array<int, string>
     */
    public function members(): array
    {
        return $this->members;
    }

    /**
     * Field names, each after the fields it needs.
     *
     * @return array<int, string>
     */
    public function buildOrder(): array
    {
        return $this->buildOrder ??= $this->sortFields('');
    }

    /**
     * The fields to compute for these members, with every field they need, in build order. A member
     * refused by $accepts is skipped along with what only it needs.
     *
     * @param  array<int, string>  $members  field and include names
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
        if ($this->frozen) {
            return $this->needs[$name] ?? [];
        }

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
     * Names what computes a field: its FieldComputer class, or `Resource::key` for a closure; null
     * for a plain field.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function builderOf(string $resourceClass, string $key): ?string
    {
        if ($resourceClass === $this->owner) {
            return $this->builders[$key] ?? null;
        }

        $computedBy = $this->fields[$key]['computedBy'] ?? null;

        return $computedBy instanceof Closure ? "{$resourceClass}::{$key}" : $computedBy;
    }

    /**
     * Where ResponseContext keeps a computed field's value: the FieldComputer class (shared between
     * Resources) or the Resource for a closure, then the field. Null for a plain field.
     *
     * @internal
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function slotOf(string $resourceClass, string $key): ?string
    {
        if ($resourceClass === $this->owner) {
            return $this->slots[$key] ?? null;
        }

        $computedBy = $this->fields[$key]['computedBy'] ?? null;

        return $computedBy === null ? null : $this->slotFor($resourceClass, $key, $computedBy);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  class-string<FieldComputer>|Closure  $computedBy
     */
    private function slotFor(string $resourceClass, string $key, string|Closure $computedBy): string
    {
        // the (builder, field) pair as one string: builder is the class, or Resource::field for a closure
        return $computedBy instanceof Closure ? "{$resourceClass}::{$key}\0{$key}" : "{$computedBy}\0{$key}";
    }

    /**
     * The `when:` of a field or include.
     */
    public function condition(string $name): ?Closure
    {
        if ($this->frozen) {
            return $this->conditions[$name] ?? null;
        }

        return $this->fields[$name]['when'] ?? $this->includes[$name]['when'] ?? null;
    }

    /**
     * `Resource::member`, the key of a member's `when:` result in ResponseContext.
     *
     * @internal
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function memberKey(string $resourceClass, string $name): string
    {
        return $resourceClass === $this->owner && isset($this->memberKeys[$name]) ? $this->memberKeys[$name] : "{$resourceClass}::{$name}";
    }

    /**
     * The checks deciding whether a member exists for a model, in order: its `when:`, then each
     * field of its `fields:` with its own checks, then whether its FieldComputer returned it. A
     * member reached twice is listed once. A step is a `when:` [member key, closure, reads the
     * request only, null], or a computed field that must have a value [slot, null, false, field].
     *
     * @internal
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<int, array{0: string, 1: Closure|null, 2: bool, 3: string|null}>
     */
    public function existenceSteps(string $resourceClass, string $member): array
    {
        if ($resourceClass === $this->owner && isset($this->memberKeys[$member])) {
            return $this->existenceSteps[$member] ??= $this->collectExistenceSteps($resourceClass, $member);
        }

        return $this->collectExistenceSteps($resourceClass, $member);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<int, array{0: string, 1: Closure|null, 2: bool, 3: string|null}>
     */
    private function collectExistenceSteps(string $resourceClass, string $member): array
    {
        $steps = [];
        $seen = [];
        $this->visitExistence($resourceClass, $member, $seen, $steps);

        return $steps;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<string, true>  $seen
     * @param  array<int, array{0: string, 1: Closure|null, 2: bool, 3: string|null}>  $steps
     */
    private function visitExistence(string $resourceClass, string $name, array &$seen, array &$steps): void
    {
        if (isset($seen[$name])) {
            return;
        }

        $seen[$name] = true;
        $when = $this->condition($name);

        if ($when !== null) {
            $steps[] = [$this->memberKey($resourceClass, $name), $when, $this->conditionReadsRequestOnly($name), null];
        }

        foreach ($this->dependencies($name) as $dependency) {
            $this->visitExistence($resourceClass, $dependency, $seen, $steps);
        }

        // a closure always gives a value, so only a FieldComputer field needs this check
        if (is_string($this->fields[$name]['computedBy'] ?? null)) {
            $steps[] = [(string) $this->slotOf($resourceClass, $name), null, false, $name];
        }
    }

    /**
     * Whether a member's `when:` only takes the request, so it is checked once instead of per model.
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
     * Tells `fn (Request $request)` from `fn ($model, $request)` and rejects any other `when:`.
     * A FormRequest type is refused because a plain Request is passed.
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
     * @return array{resource: class-string<ApiResource>|null, resolver: ResourceResolver|class-string<ResourceResolver>|null, relation: string|null, loader: class-string<VirtualIncludeLoader>|Closure|null, asList: bool, columns: array<int, string>, relations: array<int, string>, fields: array<int, string>, limit: int|null, when: Closure|null}|null
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
        return $this->includeByRelation[$relation] ?? null;
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
     * Most children a list include renders per parent.
     */
    public function includeLimit(string $name): int
    {
        return $this->includes[$name]['limit'] ?? (int) config('response-selection.limits.max_include_items');
    }

    /**
     * Whether an include's value renders as a list of children rather than one child or null.
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
