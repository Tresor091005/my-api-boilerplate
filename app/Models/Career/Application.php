<?php

declare(strict_types=1);

namespace App\Models\Career;

use App\Models\Abstract\Model;
use App\Models\User\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $career_job_id
 * @property string $user_id
 * @property string|null $cover_letter
 * @property string $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Job $job
 * @property-read User $user
 *
 * @method static Builder<static>|Application newModelQuery()
 * @method static Builder<static>|Application newQuery()
 * @method static Builder<static>|Application query()
 * @method static Builder<static>|Application whereCareerJobId($value)
 * @method static Builder<static>|Application whereCoverLetter($value)
 * @method static Builder<static>|Application whereCreatedAt($value)
 * @method static Builder<static>|Application whereId($value)
 * @method static Builder<static>|Application whereStatus($value)
 * @method static Builder<static>|Application whereUpdatedAt($value)
 * @method static Builder<static>|Application whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Application extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'career_applications';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'career_job_id',
        'user_id',
        'cover_letter',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        //
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(
            related: Job::class,
            foreignKey: 'career_job_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'user_id',
        );
    }
}
