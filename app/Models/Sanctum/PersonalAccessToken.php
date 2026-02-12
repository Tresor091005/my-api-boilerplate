<?php

declare(strict_types=1);

namespace App\Models\Sanctum;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * @property int $id
 * @property string $tokenable_type
 * @property string $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<array-key, mixed>|null $abilities
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property array<array-key, mixed>|null $metadata
 * @property-read Model|\Eloquent $tokenable
 *
 * @method static Builder<static>|PersonalAccessToken newModelQuery()
 * @method static Builder<static>|PersonalAccessToken newQuery()
 * @method static Builder<static>|PersonalAccessToken query()
 * @method static Builder<static>|PersonalAccessToken whereAbilities($value)
 * @method static Builder<static>|PersonalAccessToken whereCreatedAt($value)
 * @method static Builder<static>|PersonalAccessToken whereExpiresAt($value)
 * @method static Builder<static>|PersonalAccessToken whereId($value)
 * @method static Builder<static>|PersonalAccessToken whereLastUsedAt($value)
 * @method static Builder<static>|PersonalAccessToken whereMetadata($value)
 * @method static Builder<static>|PersonalAccessToken whereName($value)
 * @method static Builder<static>|PersonalAccessToken whereToken($value)
 * @method static Builder<static>|PersonalAccessToken whereTokenableId($value)
 * @method static Builder<static>|PersonalAccessToken whereTokenableType($value)
 * @method static Builder<static>|PersonalAccessToken whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $casts = [
        'abilities'    => 'json',
        'metadata'     => 'json',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    /**
     * Get a metadata value
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }
}
