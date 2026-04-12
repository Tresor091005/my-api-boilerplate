<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Assertions\UnitAssertion;
use Lahatre\Master\DTO\UnitFilterDTO;
use Lahatre\Master\DTO\UnitSyncDTO;
use Lahatre\Master\Http\Resources\UnitCollection;
use Lahatre\Master\Http\Resources\UnitGroupResource;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Shared\Contracts\Services\StandaloneService;
use Lahatre\Shared\Support\HandleGenerator;

class UnitService implements StandaloneService
{
    public function __construct(
        protected UnitAssertion $unitAssertion,
        protected UnitCache $unitCache
    ) {}

    public function list(UnitFilterDTO $filters): UnitCollection
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
        if ($filters->is_builtin !== null) {
            $query->whereHas('group', function ($q) use ($filters): void {
                $q->where('is_builtin', $filters->is_builtin);
            });
        }

        if ($filters->sort_by === 'group') {
            $query->join('master_unit_groups', 'master_units.group_id', '=', 'master_unit_groups.id')
                ->orderBy('master_unit_groups.name', $filters->sort_order)
                ->select('master_units.*');
        } else {
            $query->orderBy($filters->sort_by, $filters->sort_order);
        }

        $units = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return UnitCollection::make($units);
    }

    public function sync(UnitSyncDTO $dto): UnitGroupResource
    {
        return DB::transaction(function () use ($dto): UnitGroupResource {
            if ($dto->group_id) {
                $group = UnitGroup::where('is_builtin', false)
                    ->whereNotNull('organization_id')
                    ->where('organization_id', getPermissionsTeamId())
                    ->findOrFail($dto->group_id);

                $group->name = $dto->group_name ?? $group->name;
                $group->save();
            } else {
                $group = UnitGroup::create([
                    'is_builtin'      => false,
                    'name'            => $dto->group_name,
                    'organization_id' => getPermissionsTeamId(),
                ]);
            }

            /** @var Collection<int, Unit> $existingUnits */
            $existingUnits = $group->units()->get();

            if ($dto->units) {
                $this->unitAssertion->assertCanSync($dto->group_id, $dto->units, $existingUnits, $group->is_builtin);

                $now = now();

                $upsertData = $dto->units->map(function ($unitDto) use ($group, $existingUnits, $now): array {
                    if ($unitDto->id) {
                        /** @var Unit $unit */
                        $unit = $existingUnits->firstWhere('id', $unitDto->id);

                        return [
                            'id'              => $unit->id,
                            'organization_id' => $unit->organization_id,
                            'code'            => $unit->code,
                            'name'            => $unitDto->name,
                            'symbol'          => $unitDto->symbol,
                            'ratio'           => $unit->ratio,
                            'group_id'        => $group->id,
                            'created_at'      => $unit->created_at,
                            'updated_at'      => $now,
                        ];
                    }

                    return [
                        'id'              => (string) Str::uuid7(),
                        'organization_id' => getPermissionsTeamId(),
                        'code'            => HandleGenerator::generate($unitDto->name, 'master_units', 'code'),
                        'name'            => $unitDto->name,
                        'symbol'          => $unitDto->symbol,
                        'ratio'           => $unitDto->ratio,
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
