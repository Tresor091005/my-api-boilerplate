<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Data\StockLocationData;
use Lahatre\Catalog\Data\StockLocationFilterData;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Data\InventoryLocationConfigurationData;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Master\Data\AddressUpdateData;
use Lahatre\Master\Models\Address;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\HandleGenerator;

final class StockLocationService
{
    public function __construct(
        private InventoryInterface $inventoryInterface,
    ) {}

    public function paginate(StockLocationFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->stockLocationsQuery($filters)),
            $filters,
        );
    }

    public function retrieve(StockLocation $stockLocation): StockLocation
    {
        $stockLocation->load(responseRelationsToLoad());

        return $stockLocation;
    }

    public function create(StockLocationData $data): StockLocation
    {
        $stockLocation = new StockLocation;
        $stockLocation->fill([
            'organization_id' => currentOrganizationId(),
            'name'            => required($data->name),
        ]);
        $stockLocation->handle = HandleGenerator::generate(
            required($data->name),
            $stockLocation->getTable(),
            extra: ['organization_id' => $stockLocation->organization_id],
        );

        DB::transaction(function () use ($stockLocation, $data): void {
            $stockLocation->save();
            $this->inventoryInterface->createLocation(
                $stockLocation,
                InventoryLocationConfigurationData::fromArray(['is_active' => $data->isActive]),
            );
            $this->synchronizeAddress($stockLocation, $data->address);
        });

        $stockLocation->unsetRelation('address');
        $stockLocation->load(responseRelationsToLoad());

        return $stockLocation;
    }

    public function update(StockLocation $stockLocation, StockLocationData $data): StockLocation
    {
        $stockLocation->fill(withoutMissing(['name' => $data->name]));

        DB::transaction(function () use ($stockLocation, $data): void {
            $stockLocation->save();
            if (!$data->isActive instanceof MissingValue) {
                $this->inventoryInterface->updateLocation($stockLocation, [
                    'is_active' => $data->isActive,
                ]);
            }
            if (!$data->address instanceof MissingValue) {
                $this->synchronizeAddress($stockLocation, $data->address);
            }
        });

        $stockLocation->unsetRelation('address');
        $stockLocation->load(responseRelationsToLoad());

        return $stockLocation;
    }

    public function delete(StockLocation $stockLocation): void
    {
        DB::transaction(function () use ($stockLocation): void {
            $stockLocation->address()->delete();
            $this->inventoryInterface->deleteLocation($stockLocation);
            $stockLocation->delete();
        });
    }

    /** @return Builder<StockLocation> */
    private function stockLocationsQuery(StockLocationFilterData $filters): Builder
    {
        return StockLocation::query()
            ->where('organization_id', currentOrganizationId())
            ->when($filters->handle, fn (Builder $query, string $handle): Builder => $query->where('handle', 'like', "{$handle}%"))
            ->when($filters->name, fn (Builder $query, string $name): Builder => $query->where('name', 'like', "{$name}%"))
            ->when(
                $filters->isActive !== null,
                fn (Builder $query): Builder => $query->whereHas(
                    'inventoryLocation',
                    fn (Builder $inventoryLocationQuery): Builder => $inventoryLocationQuery->where(
                        'inventory_locations.is_active',
                        $filters->isActive,
                    ),
                ),
            )
            ->when(
                $filters->sortBy === 'is_active',
                fn (Builder $query): Builder => $query->addSelect([
                    'is_active' => InventoryLocation::query()
                        ->select('is_active')
                        ->whereColumn('inventory_locations.external_id', 'catalog_stock_locations.id')
                        ->where('inventory_locations.external_type', (new StockLocation)->getMorphClass())
                        ->where('inventory_locations.organization_id', currentOrganizationId())
                        ->limit(1),
                ]),
            );
    }

    private function synchronizeAddress(
        StockLocation $stockLocation,
        ?AddressCreateData $address,
    ): void {
        if ($address === null) {
            $stockLocation->address()->delete();

            return;
        }

        /** @var Address|null $existingAddress */
        $existingAddress = $stockLocation->address()->first();
        $payload = [
            'line'       => $address->line,
            'city'       => $address->city,
            'country'    => $address->country,
            'is_primary' => true,
        ];

        if ($existingAddress === null) {
            $stockLocation->addAddresses([
                AddressCreateData::fromArray($payload),
            ]);

            return;
        }

        $stockLocation->updateAddress($existingAddress, AddressUpdateData::fromArray($payload));
    }
}
