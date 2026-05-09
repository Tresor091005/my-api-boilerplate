<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\BundleFactory;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $handle
 * @property string $name
 * @property string|null $unit_code
 * @property int $step
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, BundleItem> $items
 * @property-read int|null $items_count
 * @property-read Unit|null $unit
 *
 * @method static Builder<static>|Bundle newModelQuery()
 * @method static Builder<static>|Bundle newQuery()
 * @method static Builder<static>|Bundle query()
 * @method static Builder<static>|Bundle whereCreatedAt($value)
 * @method static Builder<static>|Bundle whereHandle($value)
 * @method static Builder<static>|Bundle whereId($value)
 * @method static Builder<static>|Bundle whereIsActive($value)
 * @method static Builder<static>|Bundle whereName($value)
 * @method static Builder<static>|Bundle whereStep($value)
 * @method static Builder<static>|Bundle whereUnitCode($value)
 * @method static Builder<static>|Bundle whereUpdatedAt($value)
 * @method static BundleFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Bundle extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_bundles';

    protected $fillable = [
        'organization_id',
        'handle',
        'name',
        'unit_code',
        'step',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'handle'          => 'string',
        'name'            => 'string',
        'unit_code'       => 'string',
        'step'            => 'integer',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_code', 'code');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_id', 'id');
    }
}
