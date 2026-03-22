<?php

declare(strict_types=1);

namespace App\Models\Career;

use App\Models\Company\Company;
use App\Models\Company\CompanyMember;
use App\Models\Tag;
use Carbon\CarbonImmutable;
use Database\Factories\Career\JobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string|null $company_id
 * @property string|null $posted_by
 * @property string $title
 * @property string $description
 * @property string|null $location
 * @property bool $is_remote
 * @property numeric|null $salary
 * @property string $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Application> $applications
 * @property-read int|null $applications_count
 * @property-read Company|null $company
 * @property-read CompanyMember|null $poster
 * @property-read Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 *
 * @method static Builder<static>|Job newModelQuery()
 * @method static Builder<static>|Job newQuery()
 * @method static Builder<static>|Job onlyTrashed()
 * @method static Builder<static>|Job query()
 * @method static Builder<static>|Job whereCompanyId($value)
 * @method static Builder<static>|Job whereCreatedAt($value)
 * @method static Builder<static>|Job whereDescription($value)
 * @method static Builder<static>|Job whereId($value)
 * @method static Builder<static>|Job whereIsRemote($value)
 * @method static Builder<static>|Job whereLocation($value)
 * @method static Builder<static>|Job wherePostedBy($value)
 * @method static Builder<static>|Job wherePublishedAt($value)
 * @method static Builder<static>|Job whereSalary($value)
 * @method static Builder<static>|Job whereStatus($value)
 * @method static Builder<static>|Job whereTitle($value)
 * @method static Builder<static>|Job whereUpdatedAt($value)
 * @method static Builder<static>|Job withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Job withoutTrashed()
 * @method static JobFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Job extends Model
{
    use SharedTraits;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'career_jobs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'posted_by',
        'title',
        'description',
        'location',
        'is_remote',
        'salary',
        'status',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id'           => 'string',
            'company_id'   => 'string',
            'posted_by'    => 'string',
            'title'        => 'string',
            'description'  => 'string',
            'location'     => 'string',
            'is_remote'    => 'boolean',
            'salary'       => 'decimal:2',
            'status'       => 'string',
            'published_at' => 'immutable_datetime',
            'created_at'   => 'immutable_datetime',
            'updated_at'   => 'immutable_datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            related: Company::class,
            foreignKey: 'company_id',
        );
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(
            related: CompanyMember::class,
            foreignKey: 'posted_by',
        );
    }

    public function applications(): HasMany
    {
        return $this->hasMany(
            related: Application::class,
            foreignKey: 'career_job_id',
        );
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(
            related: Tag::class,
            name: 'taggable',
        );
    }
}
