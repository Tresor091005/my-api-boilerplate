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
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class GuestAccessChallenge extends Model
{
    use SharedTraits;

    protected $table = 'commitment_guest_access_challenges';

    protected $fillable = [
        'organization_id',
        'commitment_id',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'commitment_id'   => 'string',
        'code_hash'       => 'string',
        'attempts'        => 'integer',
        'expires_at'      => 'immutable_datetime',
        'consumed_at'     => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];
}
