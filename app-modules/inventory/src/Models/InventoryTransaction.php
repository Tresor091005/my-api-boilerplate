<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lahatre\Inventory\Database\Factories\InventoryTransactionFactory;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string $reference_type
 * @property string $reference_id
 * @property TransactionType $transaction_type
 * @property array|null $metadata
 * @property string|null $reversal_of_transaction_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model|\Eloquent $reference
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read InventoryTransaction|null $reversalOf
 * @property-read InventoryTransaction|null $reversal
 * @property-read int|null $movements_count
 *
 * @method static Builder<static>|InventoryTransaction newModelQuery()
 * @method static Builder<static>|InventoryTransaction newQuery()
 * @method static Builder<static>|InventoryTransaction query()
 * @method static Builder<static>|InventoryTransaction whereId($value)
 * @method static Builder<static>|InventoryTransaction whereOrganizationId($value)
 * @method static Builder<static>|InventoryTransaction whereTransactionType($value)
 * @method static Builder<static>|InventoryTransaction whereCreatedAt($value)
 * @method static Builder<static>|InventoryTransaction whereMetadata($value)
 * @method static Builder<static>|InventoryTransaction whereReferenceId($value)
 * @method static Builder<static>|InventoryTransaction whereReferenceType($value)
 * @method static Builder<static>|InventoryTransaction whereUpdatedAt($value)
 * @method static InventoryTransactionFactory factory($count = null, $state = [])
 * @method static Builder<static>|InventoryTransaction whereIdempotencyKey($value)
 * @method static Builder<static>|InventoryTransaction wherePayloadHash($value)
 * @method static Builder<static>|InventoryTransaction whereReversalOfTransactionId($value)
 *
 * @mixin \Eloquent
 */
class InventoryTransaction extends Model
{
    use SharedTraits;

    protected $table = 'inventory_transactions';

    protected $fillable = [
        'organization_id',
        'idempotency_key',
        'payload_hash',
        'reference_type',
        'reference_id',
        'transaction_type',
        'metadata',
        'reversal_of_transaction_id',
    ];

    protected $casts = [
        'id'                         => 'string',
        'organization_id'            => 'string',
        'idempotency_key'            => 'string',
        'payload_hash'               => 'string',
        'reference_type'             => 'string',
        'reference_id'               => 'string',
        'transaction_type'           => TransactionType::class,
        'metadata'                   => 'array',
        'reversal_of_transaction_id' => 'string',
        'created_at'                 => 'immutable_datetime',
        'updated_at'                 => 'immutable_datetime',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'transaction_id', 'id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_transaction_id', 'id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_transaction_id', 'id');
    }
}
