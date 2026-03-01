<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Assertions\UnitAssertion;
use Lahatre\Catalog\DTO\UnitFilterDTO;
use Lahatre\Catalog\DTO\UnitSyncDTO;
use Lahatre\Catalog\Http\Resources\UnitCollection;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Shared\Support\HandleGenerator;

class UnitService
{
    public function __construct(
        protected UnitAssertion $unitAssertion
    ) {}

    public function list(UnitFilterDTO $filters): UnitCollection
    {
        $query = Unit::query();

        if ($filters->code) {
            $query->where('code', 'like', "%{$filters->code}%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "%{$filters->name}%");
        }
        if ($filters->unit_group) {
            $query->where('unit_group', 'like', "%{$filters->unit_group}%");
        }
        if ($filters->is_builtin !== null) {
            $query->where('is_builtin', $filters->is_builtin);
        }
        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $units = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return UnitCollection::make($units);
    }

    public function sync(UnitSyncDTO $dto): UnitCollection
    {
        $groupHandle = Str::slug($dto->unit_group);
        $existingUnits = Unit::where('unit_group', $groupHandle)->get();

        $this->unitAssertion->assertCanSync($groupHandle, $dto->units, $existingUnits);

        $now = now();

        $upsertData = $dto->units->map(function ($unitDto) use ($groupHandle, $existingUnits, $now): array {
            if ($unitDto->id) {
                $unit = $existingUnits->firstWhere('id', $unitDto->id);

                return [
                    'id'         => $unit->id,
                    'code'       => $unit->code,
                    'name'       => $unitDto->name,
                    'symbol'     => $unitDto->symbol,
                    'ratio'      => $unit->ratio,
                    'unit_group' => $groupHandle,
                    'is_builtin' => $unit->is_builtin,
                    'is_active'  => $unitDto->is_active,
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
                'unit_group' => $groupHandle,
                'is_builtin' => false,
                'is_active'  => $unitDto->is_active,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->toArray();

        DB::transaction(fn () => Unit::upsert(
            $upsertData,
            ['id'],
            ['name', 'symbol', 'is_active', 'updated_at']
        ));

        return UnitCollection::make(
            Unit::whereIn('id', collect($upsertData)->pluck('id'))->get()
        );
    }
}
