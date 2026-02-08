<?php

declare(strict_types=1);

namespace App\Models\Company;

use App\Models\Abstract\Model;
use App\Models\Career\Job;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $website
 * @property string|null $logo_path
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Job> $jobs
 * @property-read int|null $jobs_count
 * @property-read Collection<int, CompanyMember> $members
 * @property-read int|null $members_count
 *
 * @method static Builder<static>|Company newModelQuery()
 * @method static Builder<static>|Company newQuery()
 * @method static Builder<static>|Company onlyTrashed()
 * @method static Builder<static>|Company query()
 * @method static Builder<static>|Company whereCreatedAt($value)
 * @method static Builder<static>|Company whereDescription($value)
 * @method static Builder<static>|Company whereId($value)
 * @method static Builder<static>|Company whereLogoPath($value)
 * @method static Builder<static>|Company whereName($value)
 * @method static Builder<static>|Company whereUpdatedAt($value)
 * @method static Builder<static>|Company whereWebsite($value)
 * @method static Builder<static>|Company withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Company withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Company extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'website',
        'logo_path',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(
            related: CompanyMember::class,
            foreignKey: 'company_id',
        );
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(
            related: Job::class,
            foreignKey: 'company_id',
        );
    }
}
