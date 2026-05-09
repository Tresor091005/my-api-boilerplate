<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Database\Factories\TagFactory;
use Lahatre\Shared\Support\HandleGenerator;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property int $order_col
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Taggable> $taggables
 *
 * @method static Builder<static>|Tag newModelQuery()
 * @method static Builder<static>|Tag newQuery()
 * @method static Builder<static>|Tag onlyTrashed()
 * @method static Builder<static>|Tag query()
 * @method static Builder<static>|Tag whereCreatedAt($value)
 * @method static Builder<static>|Tag whereDeletedAt($value)
 * @method static Builder<static>|Tag whereId($value)
 * @method static Builder<static>|Tag whereName($value)
 * @method static Builder<static>|Tag whereOrderCol($value)
 * @method static Builder<static>|Tag whereOrganizationId($value)
 * @method static Builder<static>|Tag whereSlug($value)
 * @method static Builder<static>|Tag whereType($value)
 * @method static Builder<static>|Tag whereUpdatedAt($value)
 * @method static Builder<static>|Tag withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Tag withoutTrashed()
 * @method static TagFactory factory($count = null, $state = [])
 *
 * @property-read int|null $taggables_count
 *
 * @mixin \Eloquent
 */
class Tag extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_tags';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'type',
        'order_col',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'name'            => 'string',
        'slug'            => 'string',
        'type'            => 'string',
        'order_col'       => 'integer',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            if (blank($tag->slug)) {
                $tag->slug = HandleGenerator::generate(
                    name: $tag->name,
                    table: 'master_tags',
                    column: 'slug',
                    extra: [
                        'organization_id' => $tag->organization_id,
                    ],
                );
            }
        });
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => str($value)->normalize()->value(),
        );
    }

    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class, 'tag_id', 'id');
    }
}
