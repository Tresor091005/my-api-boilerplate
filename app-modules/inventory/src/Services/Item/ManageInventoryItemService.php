<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services\Item;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Traits\ResolvesInventoryOrganization;
use Lahatre\Master\Contracts\MasterInterface;

class ManageInventoryItemService
{
    use ResolvesInventoryOrganization;

    public function __construct(
        protected MasterInterface $masterInterface,
    ) {}

    public function create(HasInventoryItem $model): InventoryItem
    {
        return DB::transaction(
            fn (): InventoryItem => $this->ensure(collect([$model]))->firstOrFail()
        );
    }

    /**
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     */
    public function createMany(array|Collection $models): Collection
    {
        return DB::transaction(
            fn (): Collection => $this->ensure(collect($models))
        );
    }

    /**
     * @param  array{sku?: string, is_active?: bool, deduction_strategy?: string}  $data
     */
    public function update(HasInventoryItem $model, array $data): InventoryItem
    {
        $validated = validator($data, [
            'sku'                => ['string', 'max:255'],
            'is_active'          => ['boolean'],
            'deduction_strategy' => ['nullable', Rule::enum(DeductionStrategy::class)],
        ])->validate();

        return DB::transaction(function () use ($model, $validated): InventoryItem {
            $item = $this->resolve($model);
            $item->fill([
                'sku'                => $validated['sku'] ?? $item->sku,
                'is_active'          => $validated['is_active'] ?? $item->is_active,
                'deduction_strategy' => array_key_exists('deduction_strategy', $validated) ? $validated['deduction_strategy'] : $item->deduction_strategy,
            ])->save();

            return $item;
        });
    }

    public function delete(HasInventoryItem $model): void
    {
        DB::transaction(function () use ($model): void {
            $this->resolve($model)->delete();
        });
    }

    public function resolve(HasInventoryItem $model): InventoryItem
    {
        $organizationId = $this->organizationId();
        $this->assertOrganization($model, $organizationId);

        return InventoryItem::query()
            ->where('organization_id', $organizationId)
            ->where('itemable_type', $model->getMorphClass())
            ->where('itemable_id', (string) $model->getKey())
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, HasInventoryItem>  $models
     * @return Collection<int, InventoryItem>
     */
    public function ensure(Collection $models): Collection
    {
        if ($models->isEmpty()) {
            return collect();
        }

        $organizationId = $this->organizationId();
        $models->each(function (HasInventoryItem $model) use ($organizationId): void {
            $this->assertOrganization($model, $organizationId);
        });

        $groupedModels = $models
            ->unique(fn (HasInventoryItem $model): string => $model->getMorphClass().':'.(string) $model->getKey())
            ->groupBy(fn (HasInventoryItem $model): string => $model->getMorphClass());

        $resolvedItems = collect();

        foreach ($groupedModels as $morphClass => $typedModels) {
            $externalIds = $typedModels
                ->map(fn (HasInventoryItem $model): string => (string) $model->getKey())
                ->values();

            $existingItems = InventoryItem::query()
                ->where('organization_id', $organizationId)
                ->where('itemable_type', $morphClass)
                ->whereIn('itemable_id', $externalIds)
                ->get()
                ->keyBy('itemable_id');

            $missingModels = $typedModels
                ->reject(fn (HasInventoryItem $model): bool => $existingItems->has((string) $model->getKey()))
                ->values();

            if ($missingModels->isNotEmpty()) {
                $now = now();

                InventoryItem::query()->insert(
                    $missingModels
                        ->map(fn (HasInventoryItem $model): array => [
                            'id'              => (string) Str::uuid7(),
                            'organization_id' => $organizationId,
                            'itemable_type'   => $model->getMorphClass(),
                            'itemable_id'     => (string) $model->getKey(),
                            'sku'             => $model->getSku(),
                            'is_active'       => true,
                            'base_unit_code'  => $this->masterInterface->baseUnit($model->getUnitGroupId())->code,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ])
                        ->all()
                );

                $existingItems = InventoryItem::query()
                    ->where('organization_id', $organizationId)
                    ->where('itemable_type', $morphClass)
                    ->whereIn('itemable_id', $externalIds)
                    ->get()
                    ->keyBy('itemable_id');
            }

            $resolvedItems = $resolvedItems->merge(
                $typedModels->map(fn (HasInventoryItem $model): InventoryItem => $existingItems->get((string) $model->getKey()))
            );
        }

        return $resolvedItems->values();
    }

    protected function assertOrganization(HasInventoryItem $model, string $organizationId): void
    {
        if ($model->getOrganizationId() !== $organizationId) {
            throw OrganizationScopeException::mismatch($organizationId, $model->getOrganizationId());
        }
    }
}
