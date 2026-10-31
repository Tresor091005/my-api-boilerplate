<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Illuminate\Support\Collection;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Inventory\Data\InventoryItemConfigurationUpdateData;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class BundleData
{
    /**
     * @param  MissingValue|Collection<int, BundleItemData>  $items
     * @param  bool|null  $stockTrackingRequested  on creation, whether stock tracking was asked for; null when left to the plan
     */
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|string|null $sku,
        public MissingValue|bool $isActive,
        public MissingValue|Collection $items,
        public InventoryItemConfigurationData|InventoryItemConfigurationUpdateData|MissingValue $inventory,
        public ?bool $stockTrackingRequested = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $isActive = $read->get('is_active', default: false);
        $items = $read->get('items');
        $inventory = $read->get('inventory', default: []);

        return new self(
            name: $read->get('name'),
            sku: $read->get('sku', default: null),
            isActive: $isActive instanceof MissingValue ? $isActive : (bool) $isActive,
            items: $items instanceof MissingValue
                ? $items
                : collect($items)->map(BundleItemData::fromArray(...)),
            inventory: $inventory instanceof MissingValue
                ? $inventory
                : ($missingFields === []
                    ? InventoryItemConfigurationData::fromArray($inventory)
                    : InventoryItemConfigurationUpdateData::fromArray($inventory)),
            stockTrackingRequested: is_array($inventory) && array_key_exists('stock_tracking_enabled', $inventory)
                ? (bool) $inventory['stock_tracking_enabled']
                : null,
        );
    }
}
