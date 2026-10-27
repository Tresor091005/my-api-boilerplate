<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Iam\Database\Factories\PlatformUserFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 *
 * @method static PlatformUserFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class PlatformUser extends Model
{
    use SharedTraits;

    protected $table = 'iam_platform_users';

    protected $fillable = ['user_id'];

    protected $casts = [
        'id'         => 'string',
        'user_id'    => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
