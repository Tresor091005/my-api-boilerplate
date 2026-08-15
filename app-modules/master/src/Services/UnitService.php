<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Assertions\UnitAssertion;
use Lahatre\Master\Data\UnitData;
use Lahatre\Master\Data\UnitFilterData;
use Lahatre\Master\Data\UnitUpsertData;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Shared\Support\HandleGenerator;

class UnitService
{
    public function __construct(
        protected UnitAssertion $unitAssertion,
        protected UnitCache $unitCache
    ) {}

    public function paginate(UnitFilterData $filters): CursorPaginator
    {
        $query = $this->unitsQuery($filters);

        if ($filters->sortBy === 'group') {
            return $query->cursorPaginate($filters->perPage, ['master_units.*'], 'cursor', $filters->cursor);
        }

        return stableCursorPaginate($query, $filters);
    }

    /** @return Builder<Unit> */
    private function unitsQuery(UnitFilterData $filters): Builder
    {
        $query = Unit::query()->where(function (Builder $query): void {
            $query->whereNull('organization_id')
                ->orWhere('organization_id', currentOrganizationId());
        });
        if ($filters->code) {
            $query->where('code', 'like', "$filters->code%");
        }
        if ($filters->name) {
            $query->where('master_units.name', 'like', "$filters->name%");
        }
        if ($filters->group) {
            $query->whereHas('group', function ($q) use ($filters): void {
                $q->where('name', 'like', "$filters->group%");
            });
        }
        if ($filters->isBuiltin !== null) {
            $query->whereHas('group', function ($q) use ($filters): void {
                $q->where('is_builtin', $filters->isBuiltin);
            });
        }

        if ($filters->sortBy === 'group') {
            $query->join('master_unit_groups', 'master_units.group_id', '=', 'master_unit_groups.id')
                ->select('master_units.*')
                ->orderBy('master_unit_groups.name', $filters->sortOrder)
                ->orderBy('master_units.id', $filters->sortOrder);
        }

        return $query;
    }

    public function upsert(UnitUpsertData $data): UnitGroup
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($data, $organizationId): UnitGroup {
            if ($data->groupId) {
                $group = UnitGroup::where('is_builtin', false)
                    ->where('organization_id', $organizationId)
                    ->findOrFail($data->groupId);

                $group->name = $data->groupName ?? $group->name;
                $group->save();
            } else {
                $group = UnitGroup::create([
                    'is_builtin'      => false,
                    'name'            => $data->groupName,
                    'organization_id' => $organizationId,
                ]);
            }

            /** @var Collection<int, Unit> $existingUnits */
            $existingUnits = $group->units()->get();

            if ($data->units) {
                $this->unitAssertion->assertCanUpsert($data->groupId, $data->units, $existingUnits, $group->is_builtin);

                $now = now();

                $upsertData = $data->units->map(function (UnitData $unitData) use ($group, $existingUnits, $now, $organizationId): array {
                    if ($unitData->id) {
                        /** @var Unit $unit */
                        $unit = $existingUnits->firstWhere('id', $unitData->id);

                        return [
                            'id'              => $unit->id,
                            'organization_id' => $unit->organization_id,
                            'code'            => $unit->code,
                            'name'            => $unitData->name,
                            'symbol'          => $unitData->symbol,
                            'ratio'           => $unit->ratio,
                            'group_id'        => $group->id,
                            'created_at'      => $unit->created_at,
                            'updated_at'      => $now,
                        ];
                    }

                    return [
                        'id'              => (string) Str::uuid7(),
                        'organization_id' => $organizationId,
                        'code'            => HandleGenerator::generate($unitData->name, 'master_units', 'code'),
                        'name'            => $unitData->name,
                        'symbol'          => $unitData->symbol,
                        'ratio'           => $unitData->ratio,
                        'group_id'        => $group->id,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                })->toArray();

                Unit::upsert(
                    $upsertData,
                    ['id'],
                    ['name', 'symbol', 'updated_at']
                );

                DB::afterCommit(fn () => $this->unitCache->rewarmUnits());
            }

            return $group->load(responseRelationsToLoad());
        });
    }

    // TODO : cannot delete group attached to variant ;
    // cannot delete group attached to units ;
}
