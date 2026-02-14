<?php

declare(strict_types=1);

namespace App\Models\Company;

use App\Models\Career\Job;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Support\Carbon;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Shared\Traits\HasAuthenticatableTraits;
use Lahatre\Shared\Traits\SharedTraits;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property string $id
 * @property string $company_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $password
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Company $company
 * @property-read Collection<int, Job> $jobs
 * @property-read int|null $jobs_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static Builder<static>|CompanyMember newModelQuery()
 * @method static Builder<static>|CompanyMember newQuery()
 * @method static Builder<static>|CompanyMember onlyTrashed()
 * @method static Builder<static>|CompanyMember query()
 * @method static Builder<static>|CompanyMember whereCompanyId($value)
 * @method static Builder<static>|CompanyMember whereCreatedAt($value)
 * @method static Builder<static>|CompanyMember whereDeletedAt($value)
 * @method static Builder<static>|CompanyMember whereEmail($value)
 * @method static Builder<static>|CompanyMember whereFirstName($value)
 * @method static Builder<static>|CompanyMember whereId($value)
 * @method static Builder<static>|CompanyMember whereLastName($value)
 * @method static Builder<static>|CompanyMember wherePassword($value)
 * @method static Builder<static>|CompanyMember whereUpdatedAt($value)
 * @method static Builder<static>|CompanyMember withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|CompanyMember withoutTrashed()
 *
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static Builder<static>|CompanyMember permission($permissions, bool $without = false)
 * @method static Builder<static>|CompanyMember role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|CompanyMember withoutPermission($permissions)
 * @method static Builder<static>|CompanyMember withoutRole($roles, ?string $guard = null)
 *
 * @mixin \Eloquent
 */
class CompanyMember extends Authenticatable
{
    use HasAuthenticatableTraits, SharedTraits;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            related: Company::class,
            foreignKey: 'company_id',
        );
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(
            related: Job::class,
            foreignKey: 'posted_by',
        );
    }
}
