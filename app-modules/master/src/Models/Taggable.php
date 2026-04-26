<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lahatre\Master\Database\Factories\TaggableFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $tag_id
 * @property string $taggable_type
 * @property string $taggable_id
 * @property-read Tag $tag
 * @property-read Model $taggable
 *
 * @method static Builder<static>|Taggable newModelQuery()
 * @method static Builder<static>|Taggable newQuery()
 * @method static Builder<static>|Taggable query()
 * @method static Builder<static>|Taggable whereId($value)
 * @method static Builder<static>|Taggable whereTagId($value)
 * @method static Builder<static>|Taggable whereTaggableId($value)
 * @method static Builder<static>|Taggable whereTaggableType($value)
 * @method static TaggableFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Taggable extends Pivot
{
    use SharedTraits;

    public $timestamps = false;

    protected $table = 'master_taggables';

    public $incrementing = false;

    protected $fillable = [
        'tag_id',
        'taggable_type',
        'taggable_id',
    ];

    protected $casts = [
        'id'            => 'string',
        'tag_id'        => 'string',
        'taggable_type' => 'string',
        'taggable_id'   => 'string',
    ];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id', 'id');
    }

    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }
}
