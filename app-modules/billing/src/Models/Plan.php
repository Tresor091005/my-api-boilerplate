<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Lahatre\Billing\Enums\PlanStatus;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @mixin \Eloquent
 */
class Plan extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'billing_plans';

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'id'         => 'string',
        'code'       => 'string',
        'name'       => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];

    /** @return HasMany<PlanVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }

    /** @return HasOne<PlanVersion, $this> */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(PlanVersion::class)->where('status', PlanVersionStatus::Published);
    }

    /** @return HasOne<PlanVersion, $this> */
    public function draftVersion(): HasOne
    {
        return $this->hasOne(PlanVersion::class)->where('status', PlanVersionStatus::Draft);
    }

    /** Reload the plan with the aggregates its status is computed from. */
    public function freshWithStatus(): self
    {
        return self::query()->withStatus()->findOrFail($this->getKey());
    }

    /**
     * Add is_on_sale and has_been_subscribed, from which PlanStatus::of() derives the status.
     *
     * @param  Builder<Plan>  $query
     */
    public function scopeWithStatus(Builder $query): void
    {
        $query->withExists([
            'currentVersion as is_on_sale',
            'versions as has_been_subscribed' => fn (Builder $versions): Builder => self::subscribedVersions($versions),
        ]);
    }

    /** @param  Builder<Plan>  $query */
    public function scopeWhereStatus(Builder $query, PlanStatus $status): void
    {
        match ($status) {
            PlanStatus::OnSale  => $query->whereHas('currentVersion'),
            PlanStatus::Retired => $query->whereDoesntHave('currentVersion')
                ->whereHas('versions', fn (Builder $versions): Builder => self::subscribedVersions($versions)),
            PlanStatus::Unused => $query->whereDoesntHave('currentVersion')
                ->whereDoesntHave('versions', fn (Builder $versions): Builder => self::subscribedVersions($versions)),
        };
    }

    /**
     * Versions an organization subscribed to at least once, deleted versions and subscriptions included.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $versions
     * @return Builder<TModel>
     */
    private static function subscribedVersions(Builder $versions): Builder
    {
        return $versions->withoutGlobalScope(SoftDeletingScope::class)->whereHas(
            'subscriptions',
            fn (Builder $subscriptions): Builder => $subscriptions->withoutGlobalScope(SoftDeletingScope::class),
        );
    }
}
