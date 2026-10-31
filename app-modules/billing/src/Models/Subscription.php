<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lahatre\Billing\Enums\SubscriptionStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $plan_version_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property-read PlanVersion $planVersion
 * @property-read Subscription|null $next
 */
class Subscription extends Model
{
    use SharedTraits;
    use SoftDeletes;

    public const int GRACE_DAYS = 5;

    protected $table = 'billing_subscriptions';

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
        'deleted_at'      => 'immutable_datetime',
    ];

    public function graceEndsAt(): CarbonImmutable
    {
        return $this->ends_at->addDays(self::GRACE_DAYS);
    }

    public function statusAt(CarbonImmutable $at): SubscriptionStatus
    {
        return match (true) {
            $at < $this->ends_at       => SubscriptionStatus::Active,
            $at < $this->graceEndsAt() => SubscriptionStatus::InGrace,
            default                    => SubscriptionStatus::Suspended,
        };
    }

    /** @return BelongsTo<PlanVersion, $this> */
    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function next(): HasOne
    {
        return $this->hasOne(self::class, 'organization_id', 'organization_id')
            ->where('starts_at', '>', CarbonImmutable::now());
    }

    /** @param  Builder<Subscription>  $query */
    public function scopeGivingAccessAt(Builder $query, CarbonImmutable $at): void
    {
        $query->where($query->qualifyColumn('starts_at'), '<=', $at)
            ->where($query->qualifyColumn('ends_at'), '>', $at->subDays(self::GRACE_DAYS))
            ->whereNotExists(fn (QueryBuilder $later): QueryBuilder => $later
                ->from('billing_subscriptions as later')
                ->whereColumn('later.organization_id', $query->qualifyColumn('organization_id'))
                ->whereColumn('later.starts_at', '>', $query->qualifyColumn('starts_at'))
                ->where('later.starts_at', '<=', $at)
                ->whereNull('later.deleted_at'));
    }
}
