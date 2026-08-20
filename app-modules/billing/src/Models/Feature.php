<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property FeatureType $type
 * @property string|null $resolver_key
 * @property bool $is_active
 */
class Feature extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'billing_features';

    protected $fillable = [
        'key',
        'name',
        'type',
        'resolver_key',
        'is_active',
    ];

    protected $casts = [
        'id'           => 'string',
        'key'          => 'string',
        'name'         => 'string',
        'type'         => FeatureType::class,
        'resolver_key' => 'string',
        'is_active'    => 'boolean',
        'created_at'   => 'immutable_datetime',
        'updated_at'   => 'immutable_datetime',
        'deleted_at'   => 'immutable_datetime',
    ];

    /** @return HasMany<PlanVersionFeature, $this> */
    public function planVersionFeatures(): HasMany
    {
        return $this->hasMany(PlanVersionFeature::class);
    }
}
