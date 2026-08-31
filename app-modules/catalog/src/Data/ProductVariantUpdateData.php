<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Inventory\Data\InventoryItemConfigurationUpdateData;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class ProductVariantUpdateData
{
    /**
     * @param  MissingValue|array<int, array{name: string, value: string}>  $options
     * @param  MissingValue|array<string, array<int, string>>  $labels
     */
    private function __construct(
        public MissingValue|string $sku,
        public MissingValue|bool $isActive,
        public MissingValue|array $options,
        public MissingValue|array $labels,
        public MissingValue|InventoryItemConfigurationUpdateData $inventory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $isActive = $read->get('is_active');
        $inventory = $read->get('inventory');

        return new self(
            sku: $read->get('sku'),
            isActive: $isActive instanceof MissingValue ? $isActive : (bool) $isActive,
            options: $read->get('options'),
            labels: $read->get('labels', default: []),
            inventory: $inventory instanceof MissingValue
                ? $inventory
                : InventoryItemConfigurationUpdateData::fromArray($inventory),
        );
    }

    public function catalogItem(): CatalogItemUpdateData
    {
        return new CatalogItemUpdateData(
            sku: $this->sku,
            isActive: $this->isActive,
            inventory: $this->inventory,
        );
    }
}
