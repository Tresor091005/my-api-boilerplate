<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read CatalogItem $catalogItem
 * @property-read Model|null $item
 * @property-read StockTransfer|null $transfer
 *
 * @method static StockTransferLineFactory factory($count = null, $state = [])
 * @method static Builder<static>|StockTransferLine newModelQuery()
 * @method static Builder<static>|StockTransferLine newQuery()
 * @method static Builder<static>|StockTransferLine onlyTrashed()
 * @method static Builder<static>|StockTransferLine query()
 * @method static Builder<static>|StockTransferLine whereCatalogItemId($value)
 * @method static Builder<static>|StockTransferLine whereCatalogItemType($value)
 * @method static Builder<static>|StockTransferLine whereCreatedAt($value)
 * @method static Builder<static>|StockTransferLine whereDeletedAt($value)
 * @method static Builder<static>|StockTransferLine whereDisplayUnitCode($value)
 * @method static Builder<static>|StockTransferLine whereId($value)
 * @method static Builder<static>|StockTransferLine whereOrganizationId($value)
 * @method static Builder<static>|StockTransferLine wherePosition($value)
 * @method static Builder<static>|StockTransferLine whereQuantity($value)
 * @method static Builder<static>|StockTransferLine whereStockIds($value)
 * @method static Builder<static>|StockTransferLine whereStockTransferId($value)
 * @method static Builder<static>|StockTransferLine whereStrategy($value)
 * @method static Builder<static>|StockTransferLine whereUpdatedAt($value)
 * @method static Builder<static>|StockTransferLine withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StockTransferLine withoutTrashed()
 *
 * @mixin \Eloquent
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
