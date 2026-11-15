<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services\Item;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Exceptions\InventoryItemException;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Shared\Support\OrganizationScopeResolver;

class ManageInventoryItemService
{
    public function __construct(
        protected MasterInterface $masterInterface,
        protected OrganizationScopeResolver $organizationScopeResolver,
    ) {}

    public function create(HasInventoryItem $model, ?InventoryItemConfigurationData $configuration = null): InventoryItem
    {
        return DB::transaction(
            fn (): InventoryItem => $this->ensure(
                collect([$model]),
                $configuration === null ? [] : [(string) $model->getKey() => $configuration],
            )->firstOrFail()
        );
    }

    /**
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     */
    public function createMany(array|Collection $models, array|Collection $configurations = []): Collection
    {
        return DB::transaction(
            fn (): Collection => $this->ensure(collect($models), $configurations)
        );
    }

    /**
     * @param  array{sku?: string, stock_tracking_enabled?: bool, is_expirable?: bool, deduction_strategy?: string}  $data
     */
    public function update(HasInventoryItem $model, array $data): InventoryItem
    {
        return DB::transaction(fn (): InventoryItem => $this->persistUpdate($this->resolve($model), $data));
    }

    /**
     * @param  array{sku?: string, stock_tracking_enabled?: bool, is_expirable?: bool, deduction_strategy?: string}  $data
     */
    protected function persistUpdate(InventoryItem $item, array $data): InventoryItem
    {
        $validated = validator($data, [
            'sku'                    => ['string', 'max:100'],
            'stock_tracking_enabled' => ['boolean'],
            'is_expirable'           => ['boolean'],
            'deduction_strategy'     => ['nullable', Rule::enum(DeductionStrategy::class)],
        ])->validate();

        $stockTrackingEnabled = (bool) ($validated['stock_tracking_enabled'] ?? $item->stock_tracking_enabled);

        if ($item->stock_tracking_enabled && !$stockTrackingEnabled && $item->stocks()->where('remaining', '>', 0)->exists()) {
            throw InventoryItemException::hasActiveStock($item->id);
        }

        $isExpirable = (bool) ($validated['is_expirable'] ?? $item->is_expirable);

        if ($item->is_expirable !== $isExpirable) {
            $activeStocks = $item->stocks()->where('remaining', '>', 0);

            $hasIncompatibleActiveStock = $isExpirable
                ? $activeStocks->whereNull('expiration_date')->exists()
                : $activeStocks->whereNotNull('expiration_date')->exists();

            if ($hasIncompatibleActiveStock) {
                throw ValidationException::withMessages([
                    'is_expirable' => __($isExpirable
                        ? 'inventory::validation.expirable_toggle_requires_expiration_dates'
                        : 'inventory::validation.non_expirable_toggle_prohibits_expiration_dates'),
                ]);
            }
        }

        $strategyWasProvided = array_key_exists('deduction_strategy', $validated);
        $strategy = $strategyWasProvided
                ? $validated['deduction_strategy']
                : $item->deduction_strategy;
        if (is_string($strategy)) {
            $strategy = DeductionStrategy::from($strategy);
        }

        if ($strategy === DeductionStrategy::Fifo && $isExpirable) {
            if ($strategyWasProvided) {
                throw ValidationException::withMessages([
                    'deduction_strategy' => __('inventory::validation.fifo_expirable_prohibited'),
                ]);
            }

            $strategy = null;
        }

        if ($strategy === DeductionStrategy::Fefo && !$isExpirable) {
            if ($strategyWasProvided) {
                throw ValidationException::withMessages([
                    'deduction_strategy' => __('inventory::validation.fefo_non_expirable_prohibited'),
                ]);
            }

            $strategy = null;
        }

        $this->validateConfiguration($isExpirable, $strategy);

        $item->fill([
            'sku'                    => $validated['sku'] ?? $item->sku,
            'stock_tracking_enabled' => $stockTrackingEnabled,
            'is_expirable'           => $isExpirable,
            'deduction_strategy'     => $strategy,
        ])->save();

        return $item;
    }

    protected function validateConfiguration(bool $isExpirable, ?DeductionStrategy $strategy): void
    {
        if ($strategy === DeductionStrategy::Fifo && $isExpirable) {
            throw ValidationException::withMessages([
                'deduction_strategy' => __('inventory::validation.fifo_expirable_prohibited'),
            ]);
        }

        if ($strategy === DeductionStrategy::Fefo && !$isExpirable) {
            throw ValidationException::withMessages([
                'deduction_strategy' => __('inventory::validation.fefo_non_expirable_prohibited'),
            ]);
        }
    }

    public function delete(HasInventoryItem $model): void
    {
        $this->deleteMany([$model]);
    }

    /**
     * Deletes the inventory items of the given models, all or none, with a
     * fixed number of queries whatever their count.
     *
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     *
     * @throws InventoryItemException If one of the items still holds stock.
     */
    public function deleteMany(array|Collection $models): void
    {
        $models = collect($models);

        if ($models->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($models): void {
            $organizationId = currentOrganizationId();
            $itemIds = $this->resolveMany($models)->pluck('id');

            $itemIdWithStock = InventoryStock::query()
                ->where('organization_id', $organizationId)
                ->whereIn('item_id', $itemIds)
                ->where('remaining', '>', 0)
                ->orderBy('item_id')
                ->value('item_id');

            if ($itemIdWithStock !== null) {
                throw InventoryItemException::cannotDeleteWithActiveStock((string) $itemIdWithStock);
            }

            InventoryItem::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $itemIds)
                ->delete();
        });
    }

    public function resolve(HasInventoryItem $model): InventoryItem
    {
        $organizationId = currentOrganizationId();
        $model = $this->resolveAndValidateModel($model);

        /** @var InventoryItem $item */
        $item = InventoryItem::query()
            ->where('organization_id', $organizationId)
            ->where('itemable_type', $model->getMorphClass())
            ->where('itemable_id', (string) $model->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $item;
    }

    /**
     * Locks the inventory items of the given models, after one check of their
     * organization scope for all of them.
     *
     * @param  Collection<int, HasInventoryItem>  $models
     *
     * @throws ModelNotFoundException If a model has no inventory item.
     *
     * @return Collection<int, InventoryItem>
     */
    protected function resolveMany(Collection $models): Collection
    {
        $organizationId = currentOrganizationId();
        $resolution = $this->organizationScopeResolver->resolveMany($models);

        if (is_string($resolution)) {
            throw $this->scopeException($resolution);
        }

        $items = collect();

        foreach ($resolution->groupBy(fn (HasInventoryItem $model): string => $model->getMorphClass()) as $morphClass => $typedModels) {
            $externalIds = $typedModels
                ->map(fn (HasInventoryItem $model): string => (string) $model->getKey())
                ->unique()
                ->values();
            $typedItems = InventoryItem::query()
                ->where('organization_id', $organizationId)
                ->where('itemable_type', $morphClass)
                ->whereIn('itemable_id', $externalIds)
                ->lockForUpdate()
                ->get();
            $missingIds = $externalIds->diff($typedItems->pluck('itemable_id'))->values()->all();

            if ($missingIds !== []) {
                throw new ModelNotFoundException()->setModel(InventoryItem::class, $missingIds);
            }

            $items = $items->merge($typedItems);
        }

        return $items;
    }

    /**
     * @param  Collection<int, HasInventoryItem>  $models
     * @return Collection<int, InventoryItem>
     */
    public function ensure(Collection $models, array|Collection $configurations = []): Collection
    {
        $organizationId = currentOrganizationId();

        if ($models->isEmpty()) {
            return collect();
        }

        $configurations = collect($configurations);

        $models = $models
            ->map(fn (HasInventoryItem $model): HasInventoryItem => $this->resolveAndValidateModel($model));

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
                        ->map(function (HasInventoryItem $model) use ($configurations, $organizationId, $now): array {
                            $configuration = $configurations->get((string) $model->getKey())
                                ?? new InventoryItemConfigurationData;

                            $this->validateConfiguration(
                                $configuration->isExpirable,
                                $configuration->deductionStrategy,
                            );

                            return [
                                'id'                     => (string) Str::uuid7(),
                                'organization_id'        => $organizationId,
                                'itemable_type'          => $model->getMorphClass(),
                                'itemable_id'            => (string) $model->getKey(),
                                'sku'                    => $model->getSku(),
                                'stock_tracking_enabled' => $configuration->stockTrackingEnabled,
                                'base_unit_code'         => $this->masterInterface->baseUnit($model->getUnitGroupId())->code,
                                'is_expirable'           => $configuration->isExpirable,
                                'deduction_strategy'     => $configuration->deductionStrategy?->value,
                                'created_at'             => $now,
                                'updated_at'             => $now,
                            ];
                        })
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

    protected function resolveAndValidateModel(HasInventoryItem $model): HasInventoryItem
    {
        $resolution = $this->organizationScopeResolver->resolve($model);

        if (is_string($resolution)) {
            throw $this->scopeException($resolution);
        }

        return $resolution;
    }

    private function scopeException(string $resolution): OrganizationScopeException
    {
        return $resolution === OrganizationScopeResolver::OrganizationMismatch
            ? OrganizationScopeException::mismatch()
            : OrganizationScopeException::resolutionFailed();
    }
}
