<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Declaration\ResponseDeclaration;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\Selection\SelectionNode;
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
     * Computed values, `when:` results and virtual children, one record per model.
     *
     * @var WeakMap<Model, ModelState>
     */
    private WeakMap $models;

    /**
     * Results of `when:` conditions that only read the request.
     *
     * @var array<string, bool>
     */
    private array $requestConditions = [];

    /**
     * Render plans, per selection level and Resource class.
     *
     * @var WeakMap<SelectionNode, array<class-string<ApiResource>, RenderPlan>>
     */
    private WeakMap $plans;

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
        $this->models = new WeakMap;
        $this->requestConditions = [];
        $this->plans = new WeakMap;
    }

    /**
     * What a Resource renders at one selection level in this response: the selected members whose
     * request-only `when:` accept the request, each with the checks left to make per model.
     *
     * @internal
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function renderPlan(string $resourceClass, SelectionNode $selection, Request $request): RenderPlan
    {
        $plans = $this->plans[$selection] ?? [];

        if (isset($plans[$resourceClass])) {
            return $plans[$resourceClass];
        }

        $definition = $resourceClass::definition();
        $steps = [];
        $readsConditions = false;

        foreach ($definition->members() as $name) {
            $isField = $definition->hasField($name);
            $child = $isField ? null : $selection->child($name);

            if ($isField ? $name !== 'id' && !$selection->wantsField($name) : $child === null) {
                continue;
            }

            $checks = [];

            foreach ($definition->existenceSteps($resourceClass, $name) as [$key, $when, $requestOnly]) {
                if ($when === null) {
                    $checks[] = [false, $key, null];
                } elseif (!$requestOnly) {
                    $checks[] = [true, $key, $when];
                    $readsConditions = true;
                } elseif (!($this->requestConditions[$key] ??= $when($request) === true)) {
                    // the same answer for every model: the member is left out of this response
                    continue 2;
                }
            }

            $steps[] = $isField
                ? new RenderStep($name, true, $definition->slotOf($resourceClass, $name), $checks)
                : new RenderStep(
                    $name,
                    false,
                    null,
                    $checks,
                    $child,
                    $definition->isVirtualInclude($name),
                    $definition->includeRelation($name),
                    $definition->includeResourceClass($name),
                    $definition->rendersAsList($name),
                    $definition->includeLimit($name),
                );
        }

        $plan = new RenderPlan($steps, $readsConditions);
        $plans[$resourceClass] = $plan;
        $this->plans[$selection] = $plans;

        return $plan;
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
     * @internal
     *
     * @param  array<string, string>  $slots  slot of each field asked, by field
     * @param  array<string, mixed>  $values
     */
    public function rememberComputed(Model $model, array $slots, array $values): void
    {
        $state = $this->models[$model] ??= new ModelState;

        foreach ($slots as $field => $slot) {
            if (array_key_exists($field, $values)) {
                $state->values[$slot] = $values[$field];
            } else {
                $state->absent[$slot] = true;
            }
        }
    }

    /**
     * Records the value a closure returned for one model.
     *
     * @internal
     */
    public function rememberComputedValue(Model $model, string $slot, mixed $value): void
    {
        $state = $this->models[$model] ??= new ModelState;
        $state->values[$slot] = $value;
    }

    /**
     * What the response built for this model so far, null when nothing yet. Read-only for callers.
     *
     * @internal
     */
    public function stateOf(Model $model): ?ModelState
    {
        return $this->models[$model] ?? null;
    }

    /**
     * What the response built for this model, created empty when missing.
     *
     * @internal
     */
    public function stateFor(Model $model): ModelState
    {
        return $this->models[$model] ??= new ModelState;
    }

    /**
     * Whether a per-model `when:` accepts this model. Asked once per model.
     *
     * @internal
     *
     * @param  string  $key  `Resource::member`
     */
    public function acceptsModel(string $key, Closure $when, Model $model, Request $request): bool
    {
        $state = $this->models[$model] ??= new ModelState;

        return $state->conditions[$key] ??= $when($model, $request) === true;
    }

    /**
     * Whether this field was already computed for this model.
     *
     * @internal
     */
    public function wasComputed(Model $model, string $slot): bool
    {
        $state = $this->models[$model] ?? null;

        return $state !== null && (array_key_exists($slot, $state->values) || isset($state->absent[$slot]));
    }

    /**
     * Whether the builder returned a value, even null, for this field.
     *
     * @internal
     */
    public function hasComputedValue(Model $model, string $slot): bool
    {
        $state = $this->models[$model] ?? null;

        return $state !== null && array_key_exists($slot, $state->values);
    }

    /**
     * @internal
     */
    public function computedValue(Model $model, string $slot): mixed
    {
        return ($this->models[$model] ?? null)?->values[$slot] ?? null;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function rememberVirtual(Model $parent, string $resourceClass, string $include, mixed $value): void
    {
        $state = $this->models[$parent] ??= new ModelState;
        $state->virtual[$resourceClass][$include] = $value;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function hasVirtual(Model $parent, string $resourceClass, string $include): bool
    {
        $state = $this->models[$parent] ?? null;

        return $state !== null && array_key_exists($include, $state->virtual[$resourceClass] ?? []);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function virtualValue(Model $parent, string $resourceClass, string $include): mixed
    {
        return ($this->models[$parent] ?? null)?->virtual[$resourceClass][$include] ?? null;
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

        return $this->requestConditions[$definition->memberKey($resourceClass, $member)] ??= $when($request) === true;
    }

    /**
     * Whether a field or include can appear for this request, given the fields it depends on.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function existsForRequest(ResourceDefinition $definition, string $resourceClass, string $member, Request $request): bool
    {
        foreach ($definition->existenceSteps($resourceClass, $member) as [$key, $when, $requestOnly]) {
            if ($requestOnly && !($this->requestConditions[$key] ??= $when($request) === true)) {
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
        $steps = $definition->existenceSteps($resourceClass, $member);

        if ($steps === []) {
            return true;
        }

        foreach ($steps as [$key, $when, $requestOnly]) {
            if ($when === null) {
                $state = $this->models[$item] ?? null;

                // left out when its FieldComputer ran for this model and gave it no value
                if ($state !== null && isset($state->absent[$key]) && !array_key_exists($key, $state->values)) {
                    return false;
                }
            } elseif ($requestOnly) {
                if (!($this->requestConditions[$key] ??= $when($request) === true)) {
                    return false;
                }
            } elseif (!$this->acceptsModel($key, $when, $item, $request)) {
                return false;
            }
        }

        return true;
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

        return $this->acceptsModel($definition->memberKey($resourceClass, $member), $when, $item, $request);
    }
}
