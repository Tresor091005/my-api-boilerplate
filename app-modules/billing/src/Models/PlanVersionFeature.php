<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $plan_version_id
 * @property string $feature_key
 * @property FeatureType $feature_type
 * @property int|null $allowance
 * @property list<string>|null $values
 * @property-read PlanVersion $planVersion
 */
class PlanVersionFeature extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'billing_plan_version_features';

    protected $fillable = [
        'plan_version_id',
        'feature_key',
        'feature_type',
        'allowance',
        'values',
    ];

    protected $casts = [
        'id'              => 'string',
        'feature_key'     => 'string',
        'feature_type'    => FeatureType::class,
        'plan_version_id' => 'string',
        'allowance'       => 'integer',
        'values'          => 'array',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    /** @return BelongsTo<PlanVersion, $this> */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }
}
