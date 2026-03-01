<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Catalog\DTO\UnitDataDTO;
use Lahatre\Catalog\Exceptions\UnitActiveLimitException;
use Lahatre\Catalog\Exceptions\UnitBaseDeactivationException;
use Lahatre\Catalog\Exceptions\UnitBaseRequiredException;
use Lahatre\Catalog\Exceptions\UnitBuiltInUpdateException;
use Lahatre\Catalog\Exceptions\UnitDuplicateRatioException;
use Lahatre\Catalog\Exceptions\UnitGroupMismatchException;
use Lahatre\Catalog\Exceptions\UnitRatioConflictException;
use Lahatre\Catalog\Exceptions\UnitRatioImmutableException;
use Lahatre\Catalog\Models\Unit;

class UnitAssertion
{
    /**
     * Asserts that the unit group can be synchronized.
     *
     * This method validates that:
     * - There are no duplicate ratios in the request.
     * - For new groups, exactly one unit has a ratio of 1.
     * - Existing units belong to the specified group and are not built-in.
     * - The base unit (ratio 1) is not being deactivated.
     * - Existing unit ratios are not being modified.
     * - New units do not conflict with existing ratios in the group.
     *
     * @param  string  $groupHandle  The handle of the unit group.
     * @param  Collection<int, UnitDataDTO>  $units  The collection of unit DTOs to validate.
     * @param  Collection<int, Unit>  $existingUnits  The already loaded existing units.
     *
     * @throws UnitDuplicateRatioException
     * @throws UnitBaseRequiredException
     * @throws UnitGroupMismatchException
     * @throws UnitBuiltInUpdateException
     * @throws UnitBaseDeactivationException
     * @throws UnitRatioImmutableException
     * @throws UnitRatioConflictException
     * @throws UnitActiveLimitException
     */
    public function assertCanSync(string $groupHandle, Collection $units, Collection $existingUnits): void
    {
        $this->assertUniqueRatiosInPayload($units);
        $this->assertActiveLimit($units, $existingUnits);

        $isNewGroup = $existingUnits->isEmpty();

        if ($isNewGroup) {
            $this->assertHasBaseUnitForNewGroup($units);
        } else {
            foreach ($units as $u) {
                if ($u->id) {
                    $existingUnit = $existingUnits->firstWhere('id', $u->id);

                    if (!$existingUnit) {
                        throw new UnitGroupMismatchException($u->id, $groupHandle);
                    }

                    $this->assertCanUpdateExistingUnit($existingUnit, $u);
                } else {
                    $this->assertCanAddNewUnitToGroup($existingUnits, $u, $groupHandle);
                }
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
     * Asserts that the group will not exceed the limit of active units.
     *
     * @param  Collection<int, UnitDataDTO>  $units
     * @param  Collection<int, Unit>  $existingUnits
     *
     * @throws UnitActiveLimitException
     */
    protected function assertActiveLimit(Collection $units, Collection $existingUnits): void
    {
        $payloadIds = $units->pluck('id')->filter()->toArray();

        // Logic: Total Active = (Active in DB but NOT in payload) + (Active in payload)
        // This avoids double-counting existing units that are updated in the payload.
        $stillActiveInDb = $existingUnits->where('is_active', true)
            ->whereNotIn('id', $payloadIds)
            ->count();

        $activeInPayload = $units->where('is_active', true)->count();

        if (($stillActiveInDb + $activeInPayload) > 10) {
            throw new UnitActiveLimitException(10);
        }
    }

    /**
     * Asserts that a new group contains exactly one base unit (ratio 1).
     *
     * @param  Collection<int, UnitDataDTO>  $units
     *
     * @throws UnitBaseRequiredException
     */
    protected function assertHasBaseUnitForNewGroup(Collection $units): void
    {
        $ratios = $units->pluck('ratio');

        if ($ratios->contains(null)) {
            throw new UnitBaseRequiredException();
        }

        if ($ratios->filter(fn ($r): bool => $r === 1)->count() !== 1) {
            throw new UnitBaseRequiredException();
        }
    }

    /**
     * Asserts that an existing unit can be updated with the provided data.
     *
     * Prevents modifying built-in units, changing ratios, or deactivating the base unit.
     *
     * @throws UnitBuiltInUpdateException
     * @throws UnitBaseDeactivationException
     * @throws UnitRatioImmutableException
     */
    protected function assertCanUpdateExistingUnit(Unit $existingUnit, UnitDataDTO $updateData): void
    {
        if ($existingUnit->is_builtin) {
            throw new UnitBuiltInUpdateException();
        }

        if ($existingUnit->ratio === 1 && !$updateData->is_active) {
            throw new UnitBaseDeactivationException();
        }

        if ($updateData->ratio !== null && (int) $updateData->ratio !== (int) $existingUnit->ratio) {
            throw new UnitRatioImmutableException();
        }
    }

    /**
     * Asserts that a new unit can be added to an existing group.
     *
     * Ensures the new ratio is not 1 and does not conflict with existing ratios.
     *
     * @throws UnitRatioConflictException
     */
    protected function assertCanAddNewUnitToGroup(Collection $existingUnits, UnitDataDTO $newData, string $groupHandle): void
    {
        if ($newData->ratio === null) {
            return;
        }

        $ratio = (int) $newData->ratio;

        if ($ratio === 1) {
            throw new UnitRatioConflictException(1, $groupHandle);
        }

        if ($existingUnits->contains('ratio', $ratio)) {
            throw new UnitRatioConflictException($ratio, $groupHandle);
        }
    }
}
