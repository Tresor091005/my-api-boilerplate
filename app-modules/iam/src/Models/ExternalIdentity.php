<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Iam\Database\Factories\ExternalIdentityFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $user_id
 * @property string $provider
 * @property string $issuer
 * @property string $subject
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 *
 * @method static ExternalIdentityFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class ExternalIdentity extends Model
{
    use SharedTraits;

    protected $table = 'iam_external_identities';

    protected $fillable = ['user_id', 'provider', 'issuer', 'subject'];

    protected $casts = [
        'id'         => 'string',
        'user_id'    => 'string',
        'provider'   => 'string',
        'issuer'     => 'string',
        'subject'    => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
