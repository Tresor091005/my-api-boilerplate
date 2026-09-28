<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $commitment_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class GuestAccessSession extends Model
{
    use SharedTraits;

    protected $table = 'commitment_guest_access_sessions';

    protected $fillable = [
        'organization_id',
        'commitment_id',
        'token_hash',
        'expires_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'commitment_id'   => 'string',
        'token_hash'      => 'string',
        'expires_at'      => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];
}
