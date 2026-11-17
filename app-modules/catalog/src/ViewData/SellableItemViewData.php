<?php

declare(strict_types=1);

namespace Lahatre\Catalog\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Lahatre\Catalog\Enums\CatalogItemType;

/**
 * `unit_amount` is in minor units and null when the item has no price at that level for that unit.
 *
 * @phpstan-type SellableItem array{catalog_item_id: string, item_type: string, name: string, sku: string, is_active: bool, unit_group_id: string, tax_profile_id: string|null, unit_code: string, unit_id: string|null, unit_name: string|null, price_level_id: string, price_level_name: string|null, currency_code: string|null, unit_amount: int|null, included_tax_ids: list<string>}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SellableItemViewData implements Arrayable, JsonSerializable
{
    /** @param list<string> $includedTaxIds */
    public function __construct(
        public string $catalogItemId,
        public CatalogItemType $itemType,
        public string $name,
        public string $sku,
        public bool $isActive,
        public string $unitGroupId,
        public ?string $taxProfileId,
        public string $unitCode,
        public ?string $unitId,
        public ?string $unitName,
        public string $priceLevelId,
        public ?string $priceLevelName,
        public ?string $currencyCode,
        public ?int $unitAmount,
        public array $includedTaxIds,
    ) {}

    public static function key(string $catalogItemId, string $unitCode, string $priceLevelId): string
    {
        return "{$catalogItemId}:{$unitCode}:{$priceLevelId}";
    }

    /** @return SellableItem */
    public function toArray(): array
    {
        return [
            'catalog_item_id'  => $this->catalogItemId,
            'item_type'        => $this->itemType->value,
            'name'             => $this->name,
            'sku'              => $this->sku,
            'is_active'        => $this->isActive,
            'unit_group_id'    => $this->unitGroupId,
            'tax_profile_id'   => $this->taxProfileId,
            'unit_code'        => $this->unitCode,
            'unit_id'          => $this->unitId,
            'unit_name'        => $this->unitName,
            'price_level_id'   => $this->priceLevelId,
            'price_level_name' => $this->priceLevelName,
            'currency_code'    => $this->currencyCode,
            'unit_amount'      => $this->unitAmount,
            'included_tax_ids' => $this->includedTaxIds,
        ];
    }

    /** @return SellableItem */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
