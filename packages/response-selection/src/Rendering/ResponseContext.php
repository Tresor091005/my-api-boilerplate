<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Declaration\ResponseDeclaration;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\Selection\SelectionTree;
use WeakMap;

/**
 * What the current request asked for in its response, and the values computed while building it.
 * It lives for one request only.
 */
final class ResponseContext
{
    private bool $active = false;

    private ResponseMode $mode = ResponseMode::Resource;

    private SelectionTree $selection;

    private ?ResponseDeclaration $declaration = null;

    /**
     * Computed field values, per model and per builder.
     *
     * @var WeakMap<Model, array<string, array{fields: array<string, true>, values: array<string, mixed>}>>
     */
    private WeakMap $computed;

    /**
     * Virtual include children, per parent model.
     *
     * @var WeakMap<Model, array<string, mixed>>
     */
    private WeakMap $virtual;

    /**
     * `when:` results, per model.
     *
     * @var WeakMap<Model, array<string, bool>>
     */
    private WeakMap $conditions;

    /**
     * Results of `when:` conditions that only read the request.
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
     * What the current route declares about its response, null before start().
     */
    public function declaration(): ?ResponseDeclaration
    {
        return $this->declaration;
    }

    /**
     * Records the values a builder returned for a model. Values it was not asked for are ignored.
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
     * Whether this field was already computed for this model.
     */
    public function wasComputed(Model $model, string $builder, string $field): bool
    {
        return isset(($this->computed[$model] ?? [])[$builder]['fields'][$field]);
    }

    /**
     * Whether the builder returned a value, even null, for this field.
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
     * Whether the request passes a `when:` condition that only reads the request. Checked once per
     * response.
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
     * Whether a field or include can appear for this request, given the fields it depends on.
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
     * Whether a field or include appears for this model.
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
     * Whether a field or include's `when:` condition accepts this model. Checked once per model.
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
