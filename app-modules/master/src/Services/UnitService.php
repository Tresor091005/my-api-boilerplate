<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Assertions\UnitAssertion;
use Lahatre\Master\Data\UnitData;
use Lahatre\Master\Data\UnitFilterData;
use Lahatre\Master\Data\UnitSyncData;
use Lahatre\Master\Http\Resources\UnitCollection;
use Lahatre\Master\Http\Resources\UnitGroupResource;
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

    public function list(UnitFilterData $filters): UnitCollection
    {
        $query = Unit::query()->with('group')->where(function (Builder $query): void {
            $query->whereNull('organization_id')
                ->orWhere('organization_id', getPermissionsTeamId());
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

            $units = $query->cursorPaginate($filters->perPage, ['master_units.*'], 'cursor', $filters->cursor);
        } else {
            $units = stableCursorPaginate($query, $filters);
        }

        return UnitCollection::make($units);
    }

    public function sync(UnitSyncData $data): UnitGroupResource
    {
        return DB::transaction(function () use ($data): UnitGroupResource {
            if ($data->groupId) {
                $group = UnitGroup::where('is_builtin', false)
                    ->whereNotNull('organization_id')
                    ->where('organization_id', getPermissionsTeamId())
                    ->findOrFail($data->groupId);

                $group->name = $data->groupName ?? $group->name;
                $group->save();
            } else {
                $group = UnitGroup::create([
                    'is_builtin'      => false,
                    'name'            => $data->groupName,
                    'organization_id' => getPermissionsTeamId(),
                ]);
            }

            /** @var Collection<int, Unit> $existingUnits */
            $existingUnits = $group->units()->get();

            if ($data->units) {
                $this->unitAssertion->assertCanSync($data->groupId, $data->units, $existingUnits, $group->is_builtin);

                $now = now();

                $upsertData = $data->units->map(function (UnitData $unitData) use ($group, $existingUnits, $now): array {
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
                        'organization_id' => getPermissionsTeamId(),
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

            return UnitGroupResource::make($group->load('units'));
        });
    }

    // TODO : cannot delete group attached to variant ;
    // cannot delete group attached to units ;
}
