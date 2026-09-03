<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\BundleStockOperationFactory;
use Lahatre\Catalog\Enums\BundleStockOperationStatus;
use Lahatre\Catalog\Enums\BundleStockOperationType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $bundle_id
 * @property BundleStockOperationType $type
 * @property BundleStockOperationStatus $status
 * @property int $quantity
 * @property string $location_id
 * @property array<string, mixed> $payload
 * @property array<int, array{id: string, item_type: string, item_id: string, quantity: int, display_unit_code: string}> $composition_snapshot
 * @property string|null $out_transaction_id
 * @property string|null $in_transaction_id
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Bundle $bundle
 * @property-read StockLocation $stockLocation
 *
 * @method static Builder<static>|BundleStockOperation newModelQuery()
 * @method static Builder<static>|BundleStockOperation newQuery()
 * @method static Builder<static>|BundleStockOperation query()
 * @method static Builder<static>|BundleStockOperation whereBundleId($value)
 * @method static Builder<static>|BundleStockOperation whereCreatedAt($value)
 * @method static Builder<static>|BundleStockOperation whereDeletedAt($value)
 * @method static Builder<static>|BundleStockOperation whereId($value)
 * @method static Builder<static>|BundleStockOperation whereLocationId($value)
 * @method static Builder<static>|BundleStockOperation whereOrganizationId($value)
 * @method static Builder<static>|BundleStockOperation whereStatus($value)
 * @method static Builder<static>|BundleStockOperation whereType($value)
 * @method static Builder<static>|BundleStockOperation whereUpdatedAt($value)
 * @method static BundleStockOperationFactory factory($count = null, $state = [])
 * @method static Builder<static>|BundleStockOperation onlyTrashed()
 * @method static Builder<static>|BundleStockOperation withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|BundleStockOperation withoutTrashed()
 *
 * @mixin \Eloquent
 */
class BundleStockOperation extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_bundle_stock_operations';

    protected $fillable = [
        'organization_id',
        'bundle_id',
        'type',
        'status',
        'quantity',
        'location_id',
        'payload',
        'composition_snapshot',
        'out_transaction_id',
        'in_transaction_id',
        'completed_at',
    ];

    protected $casts = [
        'id'                   => 'string',
        'organization_id'      => 'string',
        'bundle_id'            => 'string',
        'type'                 => BundleStockOperationType::class,
        'status'               => BundleStockOperationStatus::class,
        'quantity'             => 'integer',
        'location_id'          => 'string',
        'payload'              => 'array',
        'composition_snapshot' => 'array',
        'out_transaction_id'   => 'string',
        'in_transaction_id'    => 'string',
        'completed_at'         => 'immutable_datetime',
        'created_at'           => 'immutable_datetime',
        'updated_at'           => 'immutable_datetime',
        'deleted_at'           => 'immutable_datetime',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class, 'bundle_id', 'id')
            ->where('catalog_bundles.organization_id', currentOrganizationId());
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id', 'id')
            ->where('catalog_stock_locations.organization_id', currentOrganizationId());
    }
}
