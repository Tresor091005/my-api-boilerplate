<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Data\PlanData;
use Lahatre\Billing\Data\PlanFilterData;
use Lahatre\Billing\Models\Plan;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\HandleGenerator;

final class PlanService
{
    public function paginate(PlanFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->plansQuery($filters)),
            $filters,
        );
    }

    /** @return Builder<Plan> */
    private function plansQuery(PlanFilterData $filters): Builder
    {
        $query = Plan::query();

        if ($filters->code) {
            $query->where('code', 'like', $filters->code.'%');
        }
        if ($filters->name) {
            $query->where('name', 'like', $filters->name.'%');
        }
        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        return $query;
    }

    public function retrieve(Plan $plan): Plan
    {
        return $plan;
    }

    public function create(PlanData $data): Plan
    {
        $plan = new Plan;
        $name = required($data->name);

        $plan->fill([
            'name'      => $name,
            'is_active' => required($data->isActive),
        ]);
        $plan->code = HandleGenerator::generate($name, $plan->getTable(), column: 'code');

        DB::transaction(fn (): bool => $plan->save());

        return $plan->refresh();
    }

    public function update(Plan $plan, PlanData $data): Plan
    {
        $plan->fill(withoutMissing([
            'name'      => $data->name,
            'is_active' => $data->isActive,
        ]));

        DB::transaction(fn (): bool => $plan->save());

        return $plan->refresh();
    }

    public function delete(Plan $plan): void
    {
        DB::transaction(fn (): bool => $plan->delete());
    }
}
