<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Inventory\Data\InventoryItemConfigurationData;

final readonly class CatalogItemData
{
    public function __construct(
        public ?string $sku,
        public string $unitGroupId,
        public bool $isActive,
        public InventoryItemConfigurationData $inventory,
    ) {}
}
