<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $plan_version_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property-read PlanVersion $planVersion
 */
class Trial extends Model
{
    use SharedTraits;

    protected $table = 'billing_trials';

    protected $fillable = [
        'organization_id',
        'plan_version_id',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'plan_version_id' => 'string',
        'starts_at'       => 'immutable_datetime',
        'ends_at'         => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    /** @return BelongsTo<PlanVersion, $this> */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }
}
