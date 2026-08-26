<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Master\Database\Factories\NoteMentionFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $note_id
 * @property string $member_id
 * @property CarbonImmutable $mentioned_at
 * @property CarbonImmutable|null $read_at
 * @property-read Note $note
 *
 * @method static Builder<static>|NoteMention newModelQuery()
 * @method static Builder<static>|NoteMention newQuery()
 * @method static Builder<static>|NoteMention query()
 * @method static NoteMentionFactory factory($count = null, $state = [])
 * @method static Builder<static>|NoteMention whereId($value)
 * @method static Builder<static>|NoteMention whereMemberId($value)
 * @method static Builder<static>|NoteMention whereMentionedAt($value)
 * @method static Builder<static>|NoteMention whereNoteId($value)
 * @method static Builder<static>|NoteMention whereReadAt($value)
 * @method static Builder<static>|NoteMention whereOrganizationId($value)
 *
 * @mixin \Eloquent
 */
class NoteMention extends Model
{
    use SharedTraits;

    protected $table = 'master_note_mentions';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'note_id',
        'member_id',
        'mentioned_at',
        'read_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'note_id'         => 'string',
        'member_id'       => 'string',
        'mentioned_at'    => 'immutable_datetime',
        'read_at'         => 'immutable_datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id');
    }
}
