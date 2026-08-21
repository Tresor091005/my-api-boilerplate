<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Database\Factories\NoteFactory;
use Lahatre\Master\Enums\NoteKind;
use Lahatre\Master\Enums\NoteVisibility;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $notable_type
 * @property string $notable_id
 * @property string $author_id
 * @property string|null $parent_id
 * @property int $position
 * @property string $body
 * @property NoteKind $kind
 * @property CarbonImmutable|null $pinned_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $edited_at
 * @property NoteVisibility $visibility
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Model $notable
 * @property-read Note|null $parent
 * @property-read Collection<int, Note> $replies
 * @property-read Collection<int, NoteMention> $mentions
 *
 * @method static Builder<static>|Note newModelQuery()
 * @method static Builder<static>|Note newQuery()
 * @method static Builder<static>|Note query()
 * @method static NoteFactory factory($count = null, $state = [])
 * @method static Builder<static>|Note onlyTrashed()
 * @method static Builder<static>|Note whereAuthorId($value)
 * @method static Builder<static>|Note whereBody($value)
 * @method static Builder<static>|Note whereCreatedAt($value)
 * @method static Builder<static>|Note whereDeletedAt($value)
 * @method static Builder<static>|Note whereEditedAt($value)
 * @method static Builder<static>|Note whereExpiresAt($value)
 * @method static Builder<static>|Note whereId($value)
 * @method static Builder<static>|Note whereKind($value)
 * @method static Builder<static>|Note whereNotableId($value)
 * @method static Builder<static>|Note whereNotableType($value)
 * @method static Builder<static>|Note whereOrganizationId($value)
 * @method static Builder<static>|Note whereParentId($value)
 * @method static Builder<static>|Note wherePinnedAt($value)
 * @method static Builder<static>|Note wherePosition($value)
 * @method static Builder<static>|Note whereUpdatedAt($value)
 * @method static Builder<static>|Note whereVisibility($value)
 * @method static Builder<static>|Note withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Note withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Note extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_notes';

    protected $fillable = [
        'organization_id',
        'notable_type',
        'notable_id',
        'author_id',
        'parent_id',
        'position',
        'body',
        'kind',
        'pinned_at',
        'expires_at',
        'edited_at',
        'visibility',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'notable_type'    => 'string',
        'notable_id'      => 'string',
        'author_id'       => 'string',
        'parent_id'       => 'string',
        'position'        => 'integer',
        'body'            => 'string',
        'kind'            => NoteKind::class,
        'pinned_at'       => 'immutable_datetime',
        'expires_at'      => 'immutable_datetime',
        'edited_at'       => 'immutable_datetime',
        'visibility'      => NoteVisibility::class,
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('master_notes.organization_id', currentOrganizationId())
            ->withTrashed()
            ->orderBy('position');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(NoteMention::class, 'note_id')
            ->where('master_note_mentions.organization_id', currentOrganizationId());
    }
}
