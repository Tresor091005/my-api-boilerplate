<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Catalog\DTO\UnitDataDTO;
use Lahatre\Catalog\Exceptions\Unit\UnitBaseRequiredException;
use Lahatre\Catalog\Exceptions\Unit\UnitBuiltInUpdateException;
use Lahatre\Catalog\Exceptions\Unit\UnitDuplicateRatioException;
use Lahatre\Catalog\Exceptions\Unit\UnitGroupMismatchException;
use Lahatre\Catalog\Exceptions\Unit\UnitRatioConflictException;
use Lahatre\Catalog\Exceptions\Unit\UnitRatioImmutableException;
use Lahatre\Catalog\Exceptions\Unit\UnitRatioRequiredException;
use Lahatre\Catalog\Models\Unit;

class UnitAssertion
{
    /**
     * Asserts that the unit group can be synchronized.
     *
     * @param  string|null  $groupId  The ID of the unit group (null if creating).
     * @param  Collection<int, UnitDataDTO>  $units  The collection of unit DTOs to validate.
     * @param  Collection<int, Unit>  $existingUnits  The already loaded existing units.
     * @param  bool  $isGroupBuiltin  Whether the unit group is built-in.
     *
     * @throws UnitDuplicateRatioException
     * @throws UnitBaseRequiredException
     * @throws UnitGroupMismatchException
     * @throws UnitBuiltInUpdateException
     * @throws UnitRatioImmutableException
     * @throws UnitRatioConflictException
     */
    public function assertCanSync(?string $groupId, Collection $units, Collection $existingUnits, bool $isGroupBuiltin): void
    {
        if ($isGroupBuiltin) {
            throw new UnitBuiltInUpdateException();
        }

        $this->assertUniqueRatiosInPayload($units);

        $isNewGroup = $groupId === null;
        $groupLabel = $groupId ?? 'new-group';

        if ($isNewGroup) {
            $this->assertHasBaseUnit($units);
        }

        foreach ($units as $u) {
            if ($u->id) {
                $this->assertCanUpdateExistingUnit($existingUnits, $u, $groupLabel);
            } else {
                $this->assertCanAddNewUnitToGroup($existingUnits, $u, $groupLabel);
            }
        }
    }

    /**
     * Asserts that the payload does not contain duplicate ratios.
     *
     * @param  Collection<int, UnitDataDTO>  $units
     *
     * @throws UnitDuplicateRatioException
     */
    protected function assertUniqueRatiosInPayload(Collection $units): void
    {
        $ratios = $units->pluck('ratio')->filter()->toArray();
        if (count($ratios) !== count(array_unique($ratios))) {
            throw new UnitDuplicateRatioException();
        }
    }

    /**
     * Asserts that the payload contains exactly one base unit (ratio 1).
     *
     * @param  Collection<int, UnitDataDTO>  $units
     *
     * @throws UnitBaseRequiredException
     */
    protected function assertHasBaseUnit(Collection $units): void
    {
        $ratios = $units->pluck('ratio');

        if ($ratios->filter(fn ($r): bool => $r === 1)->count() !== 1) {
            throw new UnitBaseRequiredException();
        }
    }

    /**
     * Asserts that an existing unit can be updated with the provided data.
     *
     * @throws UnitBuiltInUpdateException
     * @throws UnitRatioImmutableException
     * @throws UnitGroupMismatchException
     */
    protected function assertCanUpdateExistingUnit(Collection $existingUnits, UnitDataDTO $updateData, string $groupLabel): void
    {
        $existingUnit = $existingUnits->firstWhere('id', $updateData->id);

        if (!$existingUnit) {
            throw new UnitGroupMismatchException($updateData->id, $groupLabel);
        }

        if ($updateData->ratio !== null && (int) $updateData->ratio !== (int) $existingUnit->ratio) {
            throw new UnitRatioImmutableException();
        }
    }

    /**
     * Asserts that a new unit can be added to an existing group.
     *
     * @throws UnitRatioConflictException
     * @throws UnitRatioRequiredException
     */
    protected function assertCanAddNewUnitToGroup(Collection $existingUnits, UnitDataDTO $newData, string $groupLabel): void
    {
        if ($newData->ratio === null) {
            throw new UnitRatioRequiredException();
        }

        if ($existingUnits->contains('ratio', $newData->ratio)) {
            throw new UnitRatioConflictException($newData->ratio, $groupLabel);
        }
    }
}
