<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Billing\Enums\CollectionMethod;
use Lahatre\Billing\Enums\SubscriptionStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $plan_version_id
 * @property bool $is_current
 * @property SubscriptionStatus $status
 * @property CarbonImmutable $current_period_start
 * @property CarbonImmutable $current_period_end
 * @property int $billing_anchor_day
 * @property CollectionMethod $collection_method
 * @property bool $cancel_at_period_end
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $grace_ends_at
 * @property-read PlanVersion $planVersion
 */
class Subscription extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'organization_id',
        'plan_version_id',
        'is_current',
        'status',
        'current_period_start',
        'current_period_end',
        'billing_anchor_day',
        'collection_method',
        'cancel_at_period_end',
        'cancelled_at',
        'grace_ends_at',
        'provider',
        'provider_subscription_id',
    ];

    protected $casts = [
        'id'                       => 'string',
        'organization_id'          => 'string',
        'plan_version_id'          => 'string',
        'is_current'               => 'boolean',
        'status'                   => SubscriptionStatus::class,
        'current_period_start'     => 'immutable_datetime',
        'current_period_end'       => 'immutable_datetime',
        'billing_anchor_day'       => 'integer',
        'collection_method'        => CollectionMethod::class,
        'cancel_at_period_end'     => 'boolean',
        'cancelled_at'             => 'immutable_datetime',
        'grace_ends_at'            => 'immutable_datetime',
        'provider'                 => 'string',
        'provider_subscription_id' => 'string',
        'created_at'               => 'immutable_datetime',
        'updated_at'               => 'immutable_datetime',
        'deleted_at'               => 'immutable_datetime',
    ];

    /** @return BelongsTo<PlanVersion, $this> */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }
}
