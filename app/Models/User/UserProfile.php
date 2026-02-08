<?php

declare(strict_types=1);

namespace App\Models\User;

use App\Models\Abstract\Model;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $bio
 * @property string|null $cv_path
 * @property string|null $linkedin_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 *
 * @method static Builder<static>|UserProfile newModelQuery()
 * @method static Builder<static>|UserProfile newQuery()
 * @method static Builder<static>|UserProfile query()
 * @method static Builder<static>|UserProfile whereBio($value)
 * @method static Builder<static>|UserProfile whereCreatedAt($value)
 * @method static Builder<static>|UserProfile whereCvPath($value)
 * @method static Builder<static>|UserProfile whereId($value)
 * @method static Builder<static>|UserProfile whereLinkedinUrl($value)
 * @method static Builder<static>|UserProfile whereUpdatedAt($value)
 * @method static Builder<static>|UserProfile whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserProfile extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'cv_path',
        'linkedin_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'user_id',
        );
    }
}
