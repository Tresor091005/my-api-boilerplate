<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $plan_version_id
 * @property string $feature_id
 * @property int|null $allowance
 * @property-read PlanVersion $planVersion
 * @property-read Feature $feature
 */
class PlanVersionFeature extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'billing_plan_version_features';

    protected $fillable = [
        'plan_version_id',
        'feature_id',
        'allowance',
    ];

    protected $casts = [
        'id'              => 'string',
        'feature_id'      => 'string',
        'plan_version_id' => 'string',
        'allowance'       => 'integer',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    /** @return BelongsTo<PlanVersion, $this> */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
