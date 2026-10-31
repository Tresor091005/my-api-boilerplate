<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\Data\PlanFilterData;
use Lahatre\Billing\Enums\CheckoutStatus;
use Lahatre\Billing\Enums\PlanStatus;
use Lahatre\Billing\Exceptions\PlanException;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\PlanVersionFeature;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\HandleGenerator;

final class PlanService
{
    /** @return CursorPaginator<int, Plan> */
    public function paginate(PlanFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            $this->plansQuery($filters),
            $filters,
        );
    }

    /** @return Builder<Plan> */
    private function plansQuery(PlanFilterData $filters): Builder
    {
        $query = Plan::query()->withStatus();

        if ($filters->status !== null) {
            $query->whereStatus($filters->status);
        }

        if ($filters->code) {
            $query->where('code', 'like', $filters->code.'%');
        }
        if ($filters->name) {
            $query->where('name', 'like', $filters->name.'%');
        }

        return $query;
    }

    /**
     * The plans on sale, cheapest first: the offer an organization can subscribe to.
     *
     * @return Collection<int, Plan>
     */
    public function offers(): Collection
    {
        return Plan::query()
            ->whereStatus(PlanStatus::OnSale)
            ->with('currentVersion')
            ->get()
            ->sortBy(fn (Plan $plan): int => $plan->currentVersion->price)
            ->values();
    }

    public function retrieve(Plan $plan): Plan
    {
        return $plan->freshWithStatus();
    }

    public function create(PlanData $data): Plan
    {
        $plan = new Plan;
        $name = required($data->name);

        $plan->fill(['name' => $name]);
        $plan->code = HandleGenerator::generate($name, $plan->getTable(), column: 'code');

        DB::transaction(fn (): bool => $plan->save());

        return $plan->freshWithStatus();
    }

    public function update(Plan $plan, PlanData $data): Plan
    {
        $plan->fill(withoutMissing(['name' => $data->name]));

        DB::transaction(fn (): bool => $plan->save());

        return $plan->freshWithStatus();
    }

    /**
     * Delete a plan that never served, with its versions and their grants: nothing on sale and no subscription ever.
     *
     * @throws PlanException when the plan is not unused
     */
    public function delete(Plan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $versionIds = PlanVersion::withTrashed()->where('plan_id', $plan->getKey())->lockForUpdate()->pluck('id');

            $isUnused = Plan::query()->whereKey($plan->getKey())->whereStatus(PlanStatus::Unused)->exists();
            $isBeingPaid = Checkout::query()
                ->whereIn('plan_version_id', $versionIds)
                ->where('status', CheckoutStatus::Pending)
                ->where('expires_at', '>', CarbonImmutable::now())
                ->exists();

            if (!$isUnused || $isBeingPaid) {
                throw PlanException::inUse($plan->getKey());
            }

            PlanVersionFeature::query()->whereIn('plan_version_id', $versionIds)->delete();
            PlanVersion::query()->where('plan_id', $plan->getKey())->delete();
            $plan->delete();
        });
    }
}
