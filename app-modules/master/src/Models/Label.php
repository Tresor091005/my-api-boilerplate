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
use Lahatre\Master\Database\Factories\LabelFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $value
 * @property string $slug
 * @property string $group
 * @property int $order_col
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Labelable> $labelables
 *
 * @method static Builder<static>|Label newModelQuery()
 * @method static Builder<static>|Label newQuery()
 * @method static Builder<static>|Label onlyTrashed()
 * @method static Builder<static>|Label query()
 * @method static Builder<static>|Label whereCreatedAt($value)
 * @method static Builder<static>|Label whereDeletedAt($value)
 * @method static Builder<static>|Label whereId($value)
 * @method static Builder<static>|Label whereValue($value)
 * @method static Builder<static>|Label whereOrderCol($value)
 * @method static Builder<static>|Label whereOrganizationId($value)
 * @method static Builder<static>|Label whereSlug($value)
 * @method static Builder<static>|Label whereGroup($value)
 * @method static Builder<static>|Label whereUpdatedAt($value)
 * @method static Builder<static>|Label withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Label withoutTrashed()
 * @method static LabelFactory factory($count = null, $state = [])
 *
 * @property-read int|null $labelables_count
 *
 * @mixin \Eloquent
 */
class Label extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_labels';

    protected $fillable = [
        'organization_id',
        'value',
        'slug',
        'group',
        'order_col',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'value'           => 'string',
        'slug'            => 'string',
        'group'           => 'string',
        'order_col'       => 'integer',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    protected function value(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => str($value)->normalize()->value(),
        );
    }

    public function labelables(): HasMany
    {
        return $this->hasMany(Labelable::class, 'label_id', 'id')
            ->where('master_labelables.organization_id', currentOrganizationId());
    }
}
