<?php

declare(strict_types=1);

namespace Lahatre\Master\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Master\Data\UnitData;
use Lahatre\Master\Exceptions\UnitException;
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
     * @throws UnitException If the group is built in, a base unit is missing, ratios conflict, or supplied units do not belong to the group.
     */
    public function assertCanUpsert(?string $groupId, Collection $units, Collection $existingUnits, bool $isGroupBuiltin): void
    {
        if ($isGroupBuiltin) {
            throw UnitException::builtInUpdate();
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
     * @throws UnitException If duplicate ratios are present in the supplied unit data.
     */
    protected function assertUniqueRatiosInPayload(Collection $units): void
    {
        $ratios = $units->pluck('ratio')->filter()->toArray();
        if (count($ratios) !== count(array_unique($ratios))) {
            throw UnitException::duplicateRatio();
        }
    }

    /**
     * Asserts that the payload contains exactly one base unit (ratio 1).
     *
     * @param  Collection<int, UnitData>  $units
     *
     * @throws UnitException If the supplied units do not contain exactly one base ratio.
     */
    protected function assertHasBaseUnit(Collection $units): void
    {
        $ratios = $units->pluck('ratio');

        if ($ratios->filter(fn ($r): bool => $r === 1)->count() !== 1) {
            throw UnitException::baseRequired();
        }
    }

    /**
     * Asserts that an existing unit can be updated with the provided data.
     *
     * @throws UnitException If the unit does not belong to the group or attempts to change its ratio.
     */
    protected function assertCanUpdateExistingUnit(Collection $existingUnits, UnitData $updateData, string $groupLabel): void
    {
        $existingUnit = $existingUnits->firstWhere('id', $updateData->id);

        if (!$existingUnit) {
            throw UnitException::groupMismatch($updateData->id, $groupLabel);
        }

        if ($updateData->ratio !== null && (int) $updateData->ratio !== (int) $existingUnit->ratio) {
            throw UnitException::ratioImmutable();
        }
    }

    /**
     * Asserts that a new unit can be added to an existing group.
     *
     * @throws UnitException If the new unit has no ratio, exceeds the custom limit, or conflicts with an existing ratio.
     */
    protected function assertCanAddNewUnitToGroup(Collection $existingUnits, UnitData $newData, string $groupLabel): void
    {
        if ($newData->ratio === null) {
            throw UnitException::ratioRequired();
        }

        if ($newData->ratio > Unit::MAX_CUSTOM_RATIO) {
            throw UnitException::ratioExceedsMaximum($newData->ratio, Unit::MAX_CUSTOM_RATIO);
        }

        if ($existingUnits->contains('ratio', $newData->ratio)) {
            throw UnitException::ratioConflict($newData->ratio, $groupLabel);
        }
    }
}
