<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Inventory\Data\InventoryItemConfigurationUpdateData;
use Lahatre\Shared\Data\MissingValue;

final readonly class CatalogItemUpdateData
{
    public function __construct(
        public MissingValue|string $sku,
        public MissingValue|bool $isActive,
        public MissingValue|InventoryItemConfigurationUpdateData $inventory,
    ) {}
}
