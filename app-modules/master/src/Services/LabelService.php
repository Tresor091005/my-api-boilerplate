<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Data\LabelCreateData;
use Lahatre\Master\Data\LabelFilterData;
use Lahatre\Master\Data\LabelReorderData;
use Lahatre\Master\Data\LabelUpdateData;
use Lahatre\Master\Exceptions\LabelException;
use Lahatre\Master\Models\Label;
use Lahatre\Master\Models\Labelable;
use Lahatre\Master\Traits\InteractsWithLabels;
use Lahatre\Shared\Support\HandleGenerator;
use Lahatre\Shared\Support\OrganizationScopeResolver;

class LabelService
{
    public function __construct(
        protected OrganizationScopeResolver $organizationScopeResolver,
    ) {}

    public function paginate(LabelFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate($this->labelsQuery($filters), $filters);
    }

    /** @return Builder<Label> */
    private function labelsQuery(LabelFilterData $filters): Builder
    {
        $query = Label::query()->where('organization_id', currentOrganizationId());

        if ($filters->value) {
            $query->where('value', 'like', str($filters->value)->normalize()->value().'%');
        }

        if ($filters->group) {
            $query->where('group', str($filters->group)->normalize()->value());
        }

        $sortColumn = match ($filters->sortBy) {
            'value',
            'group',
            'order_col',
            'created_at',
            'updated_at' => $filters->sortBy,
            default      => 'value',
        };

        $query->orderBy($sortColumn, $filters->sortOrder);

        return $query;
    }

    public function create(LabelCreateData $data): Collection
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(fn (): Collection => $this->ensureLabelsExist($organizationId, $this->normalizeLabelsByGroup($data->labelsByGroup)));
    }

    public function update(Label $label, LabelUpdateData $data): Label
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($label, $data, $organizationId): Label {
            $ownedLabel = Label::query()
                ->where('organization_id', $organizationId)
                ->whereKey($label->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ownedLabel->value = $data->value;
            $ownedLabel->save();

            return $ownedLabel->fresh();
        });
    }

    public function reorder(LabelReorderData $data): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($data, $organizationId): void {
            /** @var Collection<int, Label> $labels */
            $labels = Label::query()
                ->where('organization_id', $organizationId)
                ->where('group', $data->group)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $expectedLabelIds = $labels->pluck('id')->all();
            $missingLabelIds = array_values(array_diff($expectedLabelIds, $data->labelIds));
            $unexpectedLabelIds = array_values(array_diff($data->labelIds, $expectedLabelIds));

            if ($missingLabelIds !== [] || $unexpectedLabelIds !== []) {
                throw LabelException::reorderMismatch($missingLabelIds, $unexpectedLabelIds);
            }

            $case = collect($data->labelIds)
                ->map(fn (string $labelId): string => 'WHEN ? THEN ?::integer')
                ->implode(' ');
            $placeholders = implode(',', array_fill(0, count($data->labelIds), '?'));
            $bindings = [];

            foreach ($data->labelIds as $order => $labelId) {
                $bindings[] = $labelId;
                $bindings[] = $order;
            }

            $bindings[] = now();
            $bindings[] = $organizationId;
            array_push($bindings, ...$data->labelIds);

            DB::update(
                "UPDATE master_labels SET order_col = CASE id {$case} END, updated_at = ? WHERE organization_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})",
                $bindings,
            );
        });
    }

    public function delete(Label $label): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($label, $organizationId): void {
            $ownedLabel = Label::query()
                ->where('organization_id', $organizationId)
                ->whereKey($label->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $usages = Labelable::query()
                ->where('organization_id', $organizationId)
                ->where('label_id', $ownedLabel->getKey())
                ->get(['labelable_type', 'labelable_id'])
                ->map(fn (Labelable $labelable): array => [
                    'labelable_type' => $labelable->labelable_type,
                    'labelable_id'   => $labelable->labelable_id,
                ])
                ->all();

            if ($usages !== []) {
                throw LabelException::inUse($usages);
            }

            $ownedLabel->delete();
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $labelsByGroup
     */
    public function attach(Model $model, array $labelsByGroup): void
    {
        DB::transaction(function () use ($model, $labelsByGroup): void {
            $this->assertModelUsesLabels($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockLabelable($model, $organizationId);
            $normalizedLabelsByGroup = $this->normalizeLabelsByGroup($labelsByGroup);

            if ($normalizedLabelsByGroup->isEmpty()) {
                return;
            }

            $labels = $this->ensureLabelsExist($organizationId, $normalizedLabelsByGroup);

            $labelIds = $labels->pluck('id')->unique()->values()->all();
            if ($labelIds !== []) {
                $syncPayload = collect($labelIds)
                    ->mapWithKeys(fn (string $labelId): array => [
                        $labelId => [
                            'id'              => (string) Str::uuid7(),
                            'organization_id' => $organizationId,
                        ],
                    ])
                    ->all();

                if ($syncPayload !== []) {
                    $this->labelRelation($model)->syncWithoutDetaching($syncPayload);
                }
            }
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $labelsByGroup
     */
    public function detach(Model $model, array $labelsByGroup): void
    {
        DB::transaction(function () use ($model, $labelsByGroup): void {
            $this->assertModelUsesLabels($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockLabelable($model, $organizationId);
            $normalizedLabelsByGroup = $this->normalizeLabelsByGroup($labelsByGroup);

            foreach ($normalizedLabelsByGroup as $group => $values) {
                $labels = Label::query()
                    ->where('organization_id', $organizationId)
                    ->where('group', $group)
                    ->whereIn('value', $values)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('value');

                $missingValues = $values->reject(fn (string $value): bool => $labels->has($value))->values();
                if ($missingValues->isNotEmpty()) {
                    throw LabelException::notFound($group, $missingValues->all());
                }

                $labelIds = $labels->pluck('id');
                $linkedLabelIds = $this->labelRelation($model)
                    ->where('master_labels.group', $group)
                    ->whereIn('master_labels.id', $labelIds)
                    ->pluck('master_labels.id');

                $missingLinkedValues = $labels
                    ->reject(fn (Label $label): bool => $linkedLabelIds->contains($label->id))
                    ->pluck('value')
                    ->values();

                if ($missingLinkedValues->isNotEmpty()) {
                    throw LabelException::linkNotFound($group, $missingLinkedValues->all());
                }

                $this->labelRelation($model)->detach($labelIds->all());
            }
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $labelsByGroup
     */
    public function sync(Model $model, array $labelsByGroup): void
    {
        DB::transaction(function () use ($model, $labelsByGroup): void {
            $this->assertModelUsesLabels($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockLabelable($model, $organizationId);
            $this->labelRelation($model)->detach();
            $this->attach($model, $labelsByGroup);
        });
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $labels
     */
    public function syncForGroup(Model $model, string $group, Collection|array $labels): void
    {
        DB::transaction(function () use ($model, $labels, $group): void {
            $this->assertModelUsesLabels($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockLabelable($model, $organizationId);
            $normalizedGroup = str($group)->normalize()->value();
            $existingLabelIds = $this->labelRelation($model)
                ->where('master_labels.group', $normalizedGroup)
                ->pluck('master_labels.id')
                ->all();

            if ($existingLabelIds !== []) {
                $this->labelRelation($model)->detach($existingLabelIds);
            }

            $this->attach($model, [$normalizedGroup => $labels]);
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $labelsByGroup
     * @return Collection<string, Collection<int, string>>
     */
    protected function normalizeLabelsByGroup(array $labelsByGroup): Collection
    {
        /** @var Collection<string, Collection<int, string>> $normalized */
        $normalized = collect($labelsByGroup)
            ->mapWithKeys(function (mixed $labels, mixed $group): array {
                $normalizedGroup = str((string) $group)->normalize()->value();
                /** @var Collection<int, string> $normalizedLabels */
                $normalizedLabels = collect($labels)
                    ->filter(fn (mixed $label): bool => is_string($label))
                    ->map(fn (string $label): string => str($label)->normalize()->value())
                    ->filter(fn (string $label): bool => $label !== '')
                    ->unique()
                    ->values();

                return [$normalizedGroup => $normalizedLabels];
            })
            ->filter(fn (Collection $labels): bool => $labels->isNotEmpty());

        return $normalized;
    }

    /**
     * @param  Collection<string, Collection<int, string>>  $normalizedLabelsByGroup
     * @return Collection<int, Label>
     */
    protected function ensureLabelsExist(string $organizationId, Collection $normalizedLabelsByGroup): Collection
    {
        /** @var Collection<int, Label> $labels */
        $labels = collect();
        $now = now();

        foreach ($normalizedLabelsByGroup->sortKeys() as $group => $values) {
            /** @var Collection<int, Label> $existingLabels */
            $existingLabels = Label::query()
                ->where('organization_id', $organizationId)
                ->where('group', $group)
                ->lockForUpdate()
                ->get(['value', 'order_col']);

            $existingValues = $existingLabels->pluck('value');
            $missingValues = $values->reject(fn (string $value): bool => $existingValues->contains($value))->values();
            $nextOrder = ((int) ($existingLabels->max('order_col') ?? -1)) + 1;

            $rows = $missingValues->map(function (string $value) use ($organizationId, $group, $now, &$nextOrder): array {
                return [
                    'id'              => (string) Str::uuid7(),
                    'organization_id' => $organizationId,
                    'value'           => $value,
                    'slug'            => HandleGenerator::generate(
                        name: $value,
                        table: 'master_labels',
                        column: 'slug',
                        extra: ['organization_id' => $organizationId],
                    ),
                    'group'      => $group,
                    'order_col'  => $nextOrder++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            if ($rows !== []) {
                Label::query()->insertOrIgnore($rows);
            }

            $labels = $labels->merge(
                Label::query()
                    ->where('organization_id', $organizationId)
                    ->where('group', $group)
                    ->whereIn('value', $values)
                    ->orderBy('order_col')
                    ->orderBy('value')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
            );
        }

        return $labels;
    }

    protected function lockLabelable(Model $model, string $organizationId): void
    {
        $model->newQuery()
            ->where('organization_id', $organizationId)
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function resolveAndValidateOrganizationId(Model $model): string
    {
        $resolution = $this->organizationScopeResolver->resolve($model);

        if (is_string($resolution)) {
            throw match ($resolution) {
                OrganizationScopeResolver::NoModelKey,
                OrganizationScopeResolver::NoOrganizationContext,
                OrganizationScopeResolver::NoOrganizationId     => LabelException::organizationResolutionFailed(),
                OrganizationScopeResolver::OrganizationMismatch => LabelException::organizationMismatch(),
                default                                         => LabelException::organizationResolutionFailed(),
            };
        }

        return (string) $resolution->getAttribute('organization_id');
    }

    protected function assertModelUsesLabels(Model $model): void
    {
        if (!in_array(InteractsWithLabels::class, class_uses_recursive($model::class), true)) {
            throw LabelException::modelMissingInteractsWithLabelsTrait($model::class);
        }
    }

    /**
     * @return MorphToMany<Label, Model>
     */
    protected function labelRelation(Model $model): MorphToMany
    {
        if (!method_exists($model, 'labels')) {
            throw LabelException::modelMissingInteractsWithLabelsTrait($model::class);
        }

        return $model->labels();
    }
}
