<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Billing\Enums\CheckoutAction;
use Lahatre\Billing\Enums\CheckoutStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $plan_version_id
 * @property string|null $subscription_id
 * @property CheckoutAction $action
 * @property int $periods
 * @property int $total_amount
 * @property string $currency_code
 * @property CarbonImmutable $covers_from
 * @property CarbonImmutable $covers_until
 * @property CheckoutStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $confirmed_at
 * @property-read PlanVersion $planVersion
 * @property-read Subscription|null $subscription
 */
class Checkout extends Model
{
    use SharedTraits;

    public const int EXPIRES_AFTER_MINUTES = 60;

    protected $table = 'billing_checkouts';

    protected $fillable = [
        'organization_id',
        'plan_version_id',
        'subscription_id',
        'action',
        'periods',
        'total_amount',
        'currency_code',
        'covers_from',
        'covers_until',
        'status',
        'started_at',
        'expires_at',
        'confirmed_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'plan_version_id' => 'string',
        'subscription_id' => 'string',
        'action'          => CheckoutAction::class,
        'periods'         => 'integer',
        'total_amount'    => 'integer',
        'currency_code'   => 'string',
        'covers_from'     => 'immutable_datetime',
        'covers_until'    => 'immutable_datetime',
        'status'          => CheckoutStatus::class,
        'started_at'      => 'immutable_datetime',
        'expires_at'      => 'immutable_datetime',
        'confirmed_at'    => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    /** @return BelongsTo<PlanVersion, $this> */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
