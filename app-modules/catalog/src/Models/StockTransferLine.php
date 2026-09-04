<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\StockTransferLineFactory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $stock_transfer_id
 * @property CatalogItemType $catalog_item_type
 * @property string $catalog_item_id
 * @property int $position
 * @property int $quantity
 * @property string $display_unit_code
 * @property DeductionStrategy|null $strategy
 * @property list<string>|null $stock_ids
 * @property-read CatalogItem $catalogItem
 * @property-read Model|null $item
 */
class StockTransferLine extends Model
{
    /** @use HasFactory<StockTransferLineFactory> */
    use HasFactory;

    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_stock_transfer_lines';

    protected $fillable = [
        'organization_id',
        'stock_transfer_id',
        'catalog_item_type',
        'catalog_item_id',
        'position',
        'quantity',
        'display_unit_code',
        'strategy',
        'stock_ids',
    ];

    protected $casts = [
        'id'                => 'string',
        'organization_id'   => 'string',
        'stock_transfer_id' => 'string',
        'catalog_item_type' => CatalogItemType::class,
        'catalog_item_id'   => 'string',
        'position'          => 'integer',
        'quantity'          => 'integer',
        'display_unit_code' => 'string',
        'strategy'          => DeductionStrategy::class,
        'stock_ids'         => 'array',
        'created_at'        => 'immutable_datetime',
        'updated_at'        => 'immutable_datetime',
        'deleted_at'        => 'immutable_datetime',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id')
            ->where('catalog_stock_transfers.organization_id', currentOrganizationId());
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id', 'id')
            ->where('catalog_items.organization_id', currentOrganizationId());
    }

    public function item(): MorphTo
    {
        return $this->morphTo('item', 'catalog_item_type', 'catalog_item_id')
            ->where('organization_id', currentOrganizationId());
    }
}
