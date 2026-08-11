<?php

declare(strict_types=1);

namespace Lahatre\Master\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Master\Data\UnitData;
use Lahatre\Master\Exceptions\Unit\UnitBaseRequiredException;
use Lahatre\Master\Exceptions\Unit\UnitBuiltInUpdateException;
use Lahatre\Master\Exceptions\Unit\UnitDuplicateRatioException;
use Lahatre\Master\Exceptions\Unit\UnitGroupMismatchException;
use Lahatre\Master\Exceptions\Unit\UnitRatioConflictException;
use Lahatre\Master\Exceptions\Unit\UnitRatioImmutableException;
use Lahatre\Master\Exceptions\Unit\UnitRatioRequiredException;
use Lahatre\Master\Models\Unit;

class UnitAssertion
{
    /**
     * Asserts that the unit group can be synchronized.
     *
     * @param  string|null  $groupId  The ID of the unit group (null if creating).
     * @param  Collection<int, UnitData>  $units  The collection of unit data to validate.
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
     * @param  Collection<int, UnitData>  $units
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
     * @param  Collection<int, UnitData>  $units
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
    protected function assertCanUpdateExistingUnit(Collection $existingUnits, UnitData $updateData, string $groupLabel): void
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
    protected function assertCanAddNewUnitToGroup(Collection $existingUnits, UnitData $newData, string $groupLabel): void
    {
        if ($newData->ratio === null) {
            throw new UnitRatioRequiredException();
        }

        if ($existingUnits->contains('ratio', $newData->ratio)) {
            throw new UnitRatioConflictException($newData->ratio, $groupLabel);
        }
    }
}
