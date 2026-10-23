<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use WeakMap;

/**
 * Request-scoped response state, filled by ResolveResponseSelection before the controller runs:
 * the mode, the selection and the declaration of the matched action.
 * Registered with $app->scoped(): it is never carried into queued jobs or log context.
 *
 * During a response it also holds what the SelectionLoader built, never set on the models: the
 * values of computed fields, the children of virtual includes and the `when:` verdicts.
 */
final class ResponseContext
{
    private bool $active = false;

    private ResponseMode $mode = ResponseMode::Resource;

    private SelectionTree $selection;

    private ?ResponseDeclaration $declaration = null;

    /**
     * Computed fields per model, then per builder (FieldComputer class, or `Resource::key` for a
     * closure): the fields it was asked for and the values it returned.
     *
     * @var WeakMap<Model, array<string, array{fields: array<string, true>, values: array<string, mixed>}>>
     */
    private WeakMap $computed;

    /**
     * Children of virtual includes per parent model, then per `Resource::include`.
     *
     * @var WeakMap<Model, array<string, mixed>>
     */
    private WeakMap $virtual;

    /**
     * `when:` verdicts per model, then per `Resource::member`.
     *
     * @var WeakMap<Model, array<string, bool>>
     */
    private WeakMap $conditions;

    /**
     * Verdicts of request-only `when:`, per `Resource::member`.
     *
     * @var array<string, bool>
     */
    private array $requestConditions = [];

    public function __construct()
    {
        $this->selection = SelectionTree::rootFields();
        $this->forgetBuilt();
    }

    public function start(ResponseMode $mode, SelectionTree $selection, ResponseDeclaration $declaration): void
    {
        $this->active = true;
        $this->mode = $mode;
        $this->selection = $selection;
        $this->declaration = $declaration;
        $this->forgetBuilt();
    }

    public function reset(): void
    {
        $this->active = false;
        $this->mode = ResponseMode::Resource;
        $this->selection = SelectionTree::rootFields();
        $this->declaration = null;
        $this->forgetBuilt();
    }

    private function forgetBuilt(): void
    {
        $this->computed = new WeakMap;
        $this->virtual = new WeakMap;
        $this->conditions = new WeakMap;
        $this->requestConditions = [];
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function mode(): ResponseMode
    {
        return $this->mode;
    }

    public function wantsResource(): bool
    {
        return $this->mode === ResponseMode::Resource;
    }

    public function selection(): SelectionTree
    {
        return $this->selection;
    }

    /**
     * What the matched action declares, null while the context is not started.
     */
    public function declaration(): ?ResponseDeclaration
    {
        return $this->declaration;
    }

    /**
     * Records that a builder was asked for $fields on a model and what it returned; keys outside
     * $fields are dropped, a key of $fields it left out is omitted.
     *
     * @param  array<int, string>  $fields
     * @param  array<string, mixed>  $values
     */
    public function rememberComputed(Model $model, string $builder, array $fields, array $values): void
    {
        $computed = $this->computed[$model] ?? [];
        $entry = $computed[$builder] ?? ['fields' => [], 'values' => []];

        foreach ($fields as $field) {
            $entry['fields'][$field] = true;

            if (array_key_exists($field, $values)) {
                $entry['values'][$field] = $values[$field];
            }
        }

        $computed[$builder] = $entry;
        $this->computed[$model] = $computed;
    }

    /**
     * Whether the builder was already asked for this field on this model.
     */
    public function wasComputed(Model $model, string $builder, string $field): bool
    {
        return isset(($this->computed[$model] ?? [])[$builder]['fields'][$field]);
    }

    /**
     * Whether the builder returned a value (possibly null) for this field on this model.
     */
    public function hasComputedValue(Model $model, string $builder, string $field): bool
    {
        return array_key_exists($field, ($this->computed[$model] ?? [])[$builder]['values'] ?? []);
    }

    public function computedValue(Model $model, string $builder, string $field): mixed
    {
        return ($this->computed[$model] ?? [])[$builder]['values'][$field] ?? null;
    }

    /**
     * Records the children a virtual include loaded for a parent.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function rememberVirtual(Model $parent, string $resourceClass, string $include, mixed $value): void
    {
        $virtual = $this->virtual[$parent] ?? [];
        $virtual["{$resourceClass}::{$include}"] = $value;
        $this->virtual[$parent] = $virtual;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function hasVirtual(Model $parent, string $resourceClass, string $include): bool
    {
        return array_key_exists("{$resourceClass}::{$include}", $this->virtual[$parent] ?? []);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function virtualValue(Model $parent, string $resourceClass, string $include): mixed
    {
        return ($this->virtual[$parent] ?? [])["{$resourceClass}::{$include}"] ?? null;
    }

    /**
     * Whether the request passes a member's request-only `when:`, evaluated once per response; true
     * for a member without `when:` or with a per-model one.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function acceptsRequest(ResourceDefinition $definition, string $resourceClass, string $member, Request $request): bool
    {
        $when = $definition->condition($member);

        if ($when === null || !$definition->conditionReadsRequestOnly($member)) {
            return true;
        }

        return $this->requestConditions["{$resourceClass}::{$member}"] ??= $when($request) === true;
    }

    /**
     * Whether a member exists once the request-only `when:` are known: its own and those of every
     * member of its `fields:`, transitively, accept the request.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function existsForRequest(ResourceDefinition $definition, string $resourceClass, string $member, Request $request): bool
    {
        if (!$this->acceptsRequest($definition, $resourceClass, $member, $request)) {
            return false;
        }

        foreach ($definition->dependencies($member) as $dependency) {
            if (!$this->existsForRequest($definition, $resourceClass, $dependency, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a member exists for a model: its `when:` accepts it, every member of its `fields:`
     * exists for it, and a FieldComputer that was asked for it returned its key.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function exists(ResourceDefinition $definition, string $resourceClass, string $member, Model $item, Request $request): bool
    {
        if (!$this->accepts($definition, $resourceClass, $member, $item, $request)) {
            return false;
        }

        foreach ($definition->dependencies($member) as $dependency) {
            if (!$this->exists($definition, $resourceClass, $dependency, $item, $request)) {
                return false;
            }
        }

        $builder = $definition->builderOf($resourceClass, $member);

        return $builder === null || !$this->wasComputed($item, $builder, $member) || $this->hasComputedValue($item, $builder, $member);
    }

    /**
     * Whether a member's `when:` accepts a model: the request-only verdict, or the per-model one,
     * evaluated once per model.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function accepts(ResourceDefinition $definition, string $resourceClass, string $member, Model $item, Request $request): bool
    {
        $when = $definition->condition($member);

        if ($when === null) {
            return true;
        }

        if ($definition->conditionReadsRequestOnly($member)) {
            return $this->acceptsRequest($definition, $resourceClass, $member, $request);
        }

        $key = "{$resourceClass}::{$member}";
        $conditions = $this->conditions[$item] ?? [];

        if (!isset($conditions[$key])) {
            $conditions[$key] = $when($item, $request) === true;
            $this->conditions[$item] = $conditions;
        }

        return $conditions[$key];
    }
}
