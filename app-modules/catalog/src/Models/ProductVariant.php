<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\ProductVariantFactory;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Traits\InteractsWithInventoryItem;
use Lahatre\Master\Contracts\HasTags;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Traits\InteractsWithTags;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $product_id
 * @property string $sku
 * @property string $unit_group_id
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $name
 * @property-read string $options_label
 * @property-read VariantOptionValue|null $pivot
 * @property-read Collection<int, OptionValue> $optionValues
 * @property-read int|null $option_values_count
 * @property-read Product $product
 * @property-read UnitGroup|null $unitGroup
 *
 * @method static Builder<static>|ProductVariant newModelQuery()
 * @method static Builder<static>|ProductVariant newQuery()
 * @method static Builder<static>|ProductVariant query()
 * @method static Builder<static>|ProductVariant whereCreatedAt($value)
 * @method static Builder<static>|ProductVariant whereId($value)
 * @method static Builder<static>|ProductVariant whereIsActive($value)
 * @method static Builder<static>|ProductVariant whereShouldManageStock($value)
 * @method static Builder<static>|ProductVariant whereProductId($value)
 * @method static Builder<static>|ProductVariant whereSku($value)
 * @method static Builder<static>|ProductVariant whereUnitGroupId($value)
 * @method static Builder<static>|ProductVariant whereUpdatedAt($value)
 * @method static ProductVariantFactory factory($count = null, $state = [])
 *
 * @property-read InventoryItem|null $inventoryItem
 * @property-read Collection<int, InventoryStock> $inventoryItemStocks
 * @property-read int|null $inventory_item_stocks_count
 * @property-read Collection<int, InventoryStock> $activeInventoryItemStocks
 * @property-read int|null $active_inventory_item_stocks_count
 * @property-read Collection<int, InventoryStock> $inventoryItemStockSummaries
 * @property-read int|null $inventory_item_stock_summaries_count
 * @property-read Collection<int, InventoryMovement> $inventoryItemMovements
 * @property-read int|null $inventory_item_movements_count
 *
 * @method static Builder<static>|ProductVariant onlyTrashed()
 * @method static Builder<static>|ProductVariant whereOrganizationId($value)
 * @method static Builder<static>|ProductVariant withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ProductVariant withoutTrashed()
 *
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<static>|ProductVariant whereDeletedAt($value)
 *
 * @mixin \Eloquent
 */
class ProductVariant extends Model implements HasInventoryItem, HasTags
{
    use InteractsWithInventoryItem;
    use InteractsWithTags;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_product_variants';

    protected $fillable = [
        'organization_id',
        'product_id',
        'sku',
        'unit_group_id',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'product_id'      => 'string',
        'sku'             => 'string',
        'unit_group_id'   => 'string',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function getUnitGroupId(): string
    {
        return $this->unit_group_id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getOrganizationId(): string
    {
        return $this->organization_id;
    }

    protected function optionsLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->optionValues
                ->map(fn (OptionValue $optionValue): string => "[{$optionValue->option->name}: {$optionValue->value}]")
                ->implode(' ')
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $label = $this->options_label;

                return $label !== ''
                    ? "{$this->product->name} {$label}"
                    : $this->product->name;
            }
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class, 'unit_group_id', 'id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            OptionValue::class,
            'catalog_variant_option_value',
            'variant_id',
            'option_value_id'
        )->using(VariantOptionValue::class);
    }

    public function toInventoryItemableSummary(): array
    {
        return [
            'id'  => $this->id,
            'sku' => $this->sku,
        ];
    }

    public function toPriceableSummary(): array
    {
        return [
            'id'   => $this->id,
            'sku'  => $this->sku,
            'name' => $this->name,
        ];
    }
}
