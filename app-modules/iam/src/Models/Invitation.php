<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property string|null $token_hash
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Role> $roles
 */
class Invitation extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'iam_invitations';

    protected $fillable = ['organization_id', 'email', 'token_hash', 'expires_at', 'accepted_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'email'           => 'string',
        'token_hash'      => 'string',
        'expires_at'      => 'immutable_datetime',
        'accepted_at'     => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'iam_invitation_roles')->orderBy('iam_roles.id');
    }
}
