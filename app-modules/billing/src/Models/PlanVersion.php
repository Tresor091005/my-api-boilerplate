<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $plan_id
 * @property int $version
 * @property PlanVersionStatus $status
 * @property int $price
 * @property string|null $currency_code
 * @property int $billing_interval
 * @property-read Plan $plan
 * @property-read Collection<int, PlanVersionFeature> $features
 */
class PlanVersion extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'billing_plan_versions';

    protected $fillable = [
        'plan_id',
        'version',
        'status',
        'price',
        'currency_code',
        'billing_interval',
        'published_at',
    ];

    protected $casts = [
        'id'               => 'string',
        'plan_id'          => 'string',
        'version'          => 'integer',
        'status'           => PlanVersionStatus::class,
        'price'            => 'integer',
        'currency_code'    => 'string',
        'billing_interval' => 'integer',
        'published_at'     => 'immutable_datetime',
        'created_at'       => 'immutable_datetime',
        'updated_at'       => 'immutable_datetime',
        'deleted_at'       => 'immutable_datetime',
    ];

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<PlanVersionFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(PlanVersionFeature::class);
    }
}
