<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $reference_type
 * @property string $reference_id
 * @property string $transaction_type
 * @property array|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model|\Eloquent $reference
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read int|null $movements_count
 *
 * @method static Builder<static>|InventoryTransaction newModelQuery()
 * @method static Builder<static>|InventoryTransaction newQuery()
 * @method static Builder<static>|InventoryTransaction query()
 * @method static Builder<static>|InventoryTransaction whereId($value)
 * @method static Builder<static>|InventoryTransaction whereTransactionType($value)
 *
 * @mixin \Eloquent
 */
class InventoryTransaction extends Model
{
    use SharedTraits;

    protected $table = 'inventory_transactions';

    protected $fillable = [
        'reference_type',
        'reference_id',
        'transaction_type',
        'metadata',
    ];

    protected $casts = [
        'id'               => 'string',
        'reference_type'   => 'string',
        'reference_id'     => 'string',
        'transaction_type' => TransactionType::class,
        'metadata'         => 'array',
        'created_at'       => 'immutable_datetime',
        'updated_at'       => 'immutable_datetime',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'transaction_id', 'id');
    }
}
