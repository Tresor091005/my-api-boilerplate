<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Lahatre\Catalog\DTO\UnitFilterDTO;
use Lahatre\Catalog\Http\Resources\UnitCollection;
use Lahatre\Catalog\Models\Unit;

class UnitService
{
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

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $units = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return UnitCollection::make($units);
    }
}
