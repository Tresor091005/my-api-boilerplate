<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\StockTransferData;
use Lahatre\Catalog\Data\StockTransferFilterData;
use Lahatre\Catalog\Enums\StockTransferStatus;
use Lahatre\Catalog\Exceptions\StockTransferException;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Models\StockTransfer;
use Lahatre\Catalog\Models\StockTransferLine;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;

final readonly class StockTransferService
{
    public function __construct(private InventoryInterface $inventoryInterface) {}

    public function create(StockTransferData $data): StockTransfer
    {
        return DB::transaction(function () use ($data): StockTransfer {
            $this->assertLocations($data->sourceLocationId, $data->destinationLocationId);

            $transfer = StockTransfer::create([
                'organization_id'         => currentOrganizationId(),
                'source_location_id'      => $data->sourceLocationId,
                'destination_location_id' => $data->destinationLocationId,
                'status'                  => StockTransferStatus::Draft,
            ]);
            $this->replaceLines($transfer, $data);

            return $this->loadResponseRelations($transfer);
        });
    }

    public function update(StockTransfer $transfer, StockTransferData $data): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $data): StockTransfer {
            $locked = $this->lockTransfer($transfer);
            $this->assertStatus($locked, StockTransferStatus::Draft, 'updated');
            $this->assertLocations($data->sourceLocationId, $data->destinationLocationId);

            $locked->fill([
                'source_location_id'      => $data->sourceLocationId,
                'destination_location_id' => $data->destinationLocationId,
            ])->save();
            $this->replaceLines($locked, $data);

            return $this->loadResponseRelations($locked);
        });
    }

    public function delete(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $locked = $this->lockTransfer($transfer);
            $this->assertStatus($locked, StockTransferStatus::Draft, 'deleted');
            $locked->lines()->delete();
            $locked->delete();
        });
    }

    public function complete(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer): StockTransfer {
            $locked = $this->lockTransfer($transfer, withLines: true);
            if ($locked->status === StockTransferStatus::Completed) {
                return $this->loadResponseRelations($locked);
            }
            $this->assertStatus($locked, StockTransferStatus::Draft, 'completed');

            $source = $this->resolveActiveLocation($locked->source_location_id);
            $destination = $this->resolveActiveLocation($locked->destination_location_id);
            $movements = $this->prepareMovements($locked, $source, $destination);

            $transaction = $this->inventoryInterface->recordTransaction([
                'idempotency_key'  => $locked->id,
                'reference_type'   => 'catalog_stock_transfer',
                'reference_id'     => $locked->id,
                'transaction_type' => TransactionType::Transfer->value,
                'metadata'         => [
                    'stock_transfer_id' => $locked->id,
                ],
                'movements' => $movements,
            ], ['movements']);

            $locked->fill([
                'status'                   => StockTransferStatus::Completed,
                'inventory_transaction_id' => $transaction->id,
                'completed_at'             => now(),
            ])->save();

            return $this->loadResponseRelations($locked);
        });
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer): StockTransfer {
            $locked = $this->lockTransfer($transfer);
            if ($locked->status === StockTransferStatus::Cancelled) {
                return $this->loadResponseRelations($locked);
            }
            $this->assertStatus($locked, StockTransferStatus::Completed, 'cancelled');
            if ($locked->inventory_transaction_id === null) {
                throw StockTransferException::invalidState([['code' => 'transaction_missing', 'context' => ['transfer_id' => $locked->id]]]);
            }

            $reversal = $this->inventoryInterface->reverseTransaction(
                $locked->inventory_transaction_id,
                ['reason' => 'stock_transfer_cancelled', 'stock_transfer_id' => $locked->id],
                ['movements'],
            );
            $locked->fill([
                'status'                  => StockTransferStatus::Cancelled,
                'reversal_transaction_id' => $reversal->id,
                'cancelled_at'            => now(),
            ])->save();

            return $this->loadResponseRelations($locked);
        });
    }

    public function paginate(StockTransferFilterData $filters): CursorPaginator
    {
        $query = StockTransfer::query()
            ->where('organization_id', currentOrganizationId())
            ->when($filters->status, fn ($query, $status) => $query->where('status', $status))
            ->with($this->transferResponseRelations());

        return stableCursorPaginate($query, $filters, tieBreakerColumn: 'catalog_stock_transfers.id');
    }

    public function retrieve(StockTransfer $transfer): StockTransfer
    {
        return StockTransfer::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($transfer->id)
            ->with($this->transferResponseRelations())
            ->firstOrFail();
    }

    private function lockTransfer(StockTransfer $transfer, bool $lock = true, bool $withLines = false): StockTransfer
    {
        $query = StockTransfer::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($transfer->id);

        if ($withLines) {
            $query->with('lines');
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var StockTransfer|null $resolved */
        $resolved = $query->firstOrFail();

        return $resolved;
    }

    private function assertLocations(string $sourceId, string $destinationId): void
    {
        if ($sourceId === $destinationId) {
            throw StockTransferException::invalidState([['code' => 'same_location', 'context' => ['location_id' => $sourceId]]]);
        }
        $count = StockLocation::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', [$sourceId, $destinationId])
            ->whereHas('inventoryLocation', function (Builder $query): void {
                $query->where('inventory_locations.is_active', true);
            })
            ->count();
        if ($count !== 2) {
            throw (new ModelNotFoundException)->setModel(StockLocation::class);
        }
    }

    private function resolveActiveLocation(string $id): InventoryLocation
    {
        $location = StockLocation::query()->where('organization_id', currentOrganizationId())->whereKey($id)
            ->with('inventoryLocation')->lockForUpdate()->first();
        if (!$location instanceof StockLocation || !$location->inventoryLocation instanceof InventoryLocation) {
            throw (new ModelNotFoundException)->setModel(StockLocation::class, [$id]);
        }
        if (!$location->inventoryLocation->is_active) {
            throw StockTransferException::invalidState([['code' => 'location_inactive', 'context' => ['location_id' => $id]]]);
        }

        return $location->inventoryLocation;
    }

    private function prepareMovements(StockTransfer $transfer, InventoryLocation $source, InventoryLocation $destination): array
    {
        $ids = $transfer->lines->pluck('catalog_item_id');
        $items = CatalogItem::query()->where('organization_id', currentOrganizationId())->whereIn('id', $ids)
            ->with('inventoryItem')->lockForUpdate()->get()->keyBy('id');
        $errors = [];
        $movements = [];
        foreach ($transfer->lines as $line) {
            /** @var CatalogItem|null $catalogItem */
            $catalogItem = $items->get($line->catalog_item_id);
            if (!$catalogItem instanceof CatalogItem || !$catalogItem->item_type->isStockable() || !$catalogItem->is_active) {
                $errors[] = ['code' => 'catalog_item_invalid', 'context' => ['item_id' => $line->catalog_item_id]];
                continue;
            }
            if ($catalogItem->item_type !== $line->catalog_item_type) {
                $errors[] = ['code' => 'catalog_item_invalid', 'context' => ['item_id' => $line->catalog_item_id]];
                continue;
            }
            /** @var InventoryItem|null $inventoryItem */
            $inventoryItem = $catalogItem->inventoryItem;
            if (!$inventoryItem instanceof InventoryItem || !$inventoryItem->stock_tracking_enabled) {
                $errors[] = ['code' => 'catalog_item_tracking_disabled', 'context' => ['item_id' => $line->catalog_item_id]];
                continue;
            }
            $movements[] = [
                'item_id'        => $inventoryItem->id,
                'location_id'    => $source->id,
                'to_location_id' => $destination->id,
                'quantity'       => $line->quantity,
                'unit_code'      => $line->display_unit_code,
                'strategy'       => $line->strategy?->value,
                'stock_ids'      => $line->stock_ids,
            ];
        }
        if ($errors !== []) {
            throw StockTransferException::invalidState($errors);
        }

        return $movements;
    }

    private function replaceLines(StockTransfer $transfer, StockTransferData $data): void
    {
        $catalogItemIds = $data->lines->pluck('catalogItemId');
        if ($catalogItemIds->duplicates()->isNotEmpty()) {
            throw StockTransferException::invalidState([['code' => 'duplicate_line', 'context' => ['transfer_id' => $transfer->id]]]);
        }

        /** @var Collection<string, CatalogItem> $catalogItems */
        $catalogItems = CatalogItem::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', $catalogItemIds)
            ->with('inventoryItem')
            ->get()
            ->keyBy('id');
        $errors = [];

        foreach ($data->lines as $line) {
            $catalogItem = $catalogItems->get($line->catalogItemId);

            if (!$catalogItem instanceof CatalogItem
                || !$catalogItem->item_type->isStockable()
                || !$catalogItem->inventoryItem instanceof InventoryItem
            ) {
                $errors[] = [
                    'code'    => 'catalog_item_invalid',
                    'context' => ['item_id' => $line->catalogItemId],
                ];
            }
        }

        if ($errors !== []) {
            throw StockTransferException::invalidState($errors);
        }

        $transfer->lines()->forceDelete();
        $now = now();
        $rows = [];

        foreach ($data->lines as $position => $line) {
            $catalogItem = $catalogItems->get($line->catalogItemId);

            $rows[] = [
                'id'                => (string) Str::uuid7(),
                'organization_id'   => currentOrganizationId(),
                'stock_transfer_id' => $transfer->id,
                'catalog_item_type' => $catalogItem->item_type,
                'catalog_item_id'   => $line->catalogItemId,
                'position'          => $position,
                'quantity'          => $line->quantity,
                'display_unit_code' => $line->unitCode,
                'strategy'          => $line->strategy,
                'stock_ids'         => $line->stockIds === []
                    ? null
                    : json_encode($line->stockIds, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        StockTransferLine::insert($rows);
    }

    private function loadResponseRelations(StockTransfer $transfer): StockTransfer
    {
        $transfer->unsetRelation('lines');

        return $transfer->load($this->transferResponseRelations());
    }

    /** @return array<string, mixed> */
    private function transferResponseRelations(): array
    {
        $relations = responseRelationsToLoad();
        $loads = [];
        $catalogItemLoader = function (MorphTo $relation): void {
            $relation->morphWith([
                ProductVariant::class => ['catalogItem', 'product', 'optionValues.option'],
                Bundle::class         => ['catalogItem', 'items.component'],
            ]);
        };

        foreach ($relations as $relation) {
            if ($relation === 'lines.item') {
                $loads[$relation] = $catalogItemLoader;
                continue;
            }

            $loads[] = $relation;
        }

        return $loads;
    }

    private function assertStatus(StockTransfer $transfer, StockTransferStatus $expected, string $action): void
    {
        if ($transfer->status !== $expected) {
            throw StockTransferException::invalidTransition($transfer->status->value, $action);
        }
    }
}
