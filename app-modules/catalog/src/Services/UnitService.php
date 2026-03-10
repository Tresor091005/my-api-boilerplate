<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Assertions\UnitAssertion;
use Lahatre\Catalog\DTO\UnitFilterDTO;
use Lahatre\Catalog\DTO\UnitSyncDTO;
use Lahatre\Catalog\Http\Resources\UnitCollection;
use Lahatre\Catalog\Http\Resources\UnitGroupResource;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Catalog\Models\UnitGroup;
use Lahatre\Shared\Contracts\Services\StandaloneService;
use Lahatre\Shared\Support\HandleGenerator;

class UnitService implements StandaloneService
{
    public function __construct(
        protected UnitAssertion $unitAssertion
    ) {}

    public function list(UnitFilterDTO $filters): UnitCollection
    {
        $query = Unit::query()->with('group');

        if ($filters->code) {
            $query->where('code', 'like', "%{$filters->code}%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "%{$filters->name}%");
        }
        if ($filters->group) {
            $query->whereHas('group', function ($q) use ($filters): void {
                $q->where('name', 'like', "%{$filters->group}%");
            });
        }
        if ($filters->is_builtin !== null) {
            $query->whereHas('group', function ($q) use ($filters): void {
                $q->where('is_builtin', $filters->is_builtin);
            });
        }

        if ($filters->sort_by === 'group') {
            $query->join('catalog_unit_groups', 'catalog_units.group_id', '=', 'catalog_unit_groups.id')
                ->orderBy('catalog_unit_groups.name', $filters->sort_order)
                ->select('catalog_units.*');
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
                $group = UnitGroup::where('is_builtin', false)->findOrFail($dto->group_id);
                $group->name = $dto->group_name ?? $group->name;
                $group->save();
            } else {
                $group = UnitGroup::create([
                    'is_builtin' => false,
                    'name'       => $dto->group_name,
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
                            'id'         => $unit->id,
                            'code'       => $unit->code,
                            'name'       => $unitDto->name,
                            'symbol'     => $unitDto->symbol,
                            'ratio'      => $unit->ratio,
                            'group_id'   => $group->id,
                            'created_at' => $unit->created_at,
                            'updated_at' => $now,
                        ];
                    }

                    return [
                        'id'         => (string) Str::uuid7(),
                        'code'       => HandleGenerator::generate($unitDto->name, 'catalog_units', 'code'),
                        'name'       => $unitDto->name,
                        'symbol'     => $unitDto->symbol,
                        'ratio'      => $unitDto->ratio,
                        'group_id'   => $group->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->toArray();

                Unit::upsert(
                    $upsertData,
                    ['id'],
                    ['name', 'symbol', 'updated_at']
                );
            }

            return UnitGroupResource::make($group->load('units'));
        });
    }

    // TODO : cannot delete group attached to variant ;
    // cannot delete group attached to units ;
}
