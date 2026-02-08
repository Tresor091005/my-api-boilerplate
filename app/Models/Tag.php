<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Abstract\Model;
use App\Models\Career\Job;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property string $id
 * @property string $type
 * @property string $value
 * @property string $slug
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Job> $jobs
 * @property-read int|null $jobs_count
 *
 * @method static Builder<static>|Tag newModelQuery()
 * @method static Builder<static>|Tag newQuery()
 * @method static Builder<static>|Tag query()
 * @method static Builder<static>|Tag whereCreatedAt($value)
 * @method static Builder<static>|Tag whereId($value)
 * @method static Builder<static>|Tag whereSlug($value)
 * @method static Builder<static>|Tag whereType($value)
 * @method static Builder<static>|Tag whereUpdatedAt($value)
 * @method static Builder<static>|Tag whereValue($value)
 *
 * @mixin \Eloquent
 */
class Tag extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'value',
        'type',
    ];

    public function jobs(): MorphToMany
    {
        return $this->morphedByMany(
            related: Job::class,
            name: 'taggable',
        );
    }
}
