<?php

declare(strict_types=1);

namespace Lahatre\Sale\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * The goods of a sale that leave, or are to leave, together.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $sale_id
 * @property string $number
 * @property FulfillmentStatus $status
 * @property string|null $inventory_transaction_id
 * @property string|null $reversal_transaction_id
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Sale $sale
 * @property-read Collection<int, FulfillmentLine> $lines
 *
 * @mixin \Eloquent
 */
class Fulfillment extends Model
{
    use SharedTraits;

    protected $table = 'sale_fulfillments';

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'organization_id',
        'sale_id',
        'number',
        'status',
        'inventory_transaction_id',
        'reversal_transaction_id',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'id'                       => 'string',
        'organization_id'          => 'string',
        'sale_id'                  => 'string',
        'number'                   => 'string',
        'status'                   => FulfillmentStatus::class,
        'inventory_transaction_id' => 'string',
        'reversal_transaction_id'  => 'string',
        'completed_at'             => 'immutable_datetime',
        'cancelled_at'             => 'immutable_datetime',
        'created_at'               => 'immutable_datetime',
        'updated_at'               => 'immutable_datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)
            ->where('sale_sales.organization_id', currentOrganizationId());
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FulfillmentLine::class)
            ->where('sale_fulfillment_lines.organization_id', currentOrganizationId());
    }
}
