<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Illuminate\Support\Collection;
use Lahatre\Master\Models\Unit;
use Lahatre\Tax\Exceptions\TaxException;

/**
 * Pairs a sold quantity with the ratios of its sale unit and of each
 * applicable per-unit tax unit. Whether the sale unit suits the item sold is
 * the caller's concern; only the tax unit must be of the sale unit's group.
 */
final readonly class TaxQuantityResolver
{
    /**
     * @param  Collection<string, Unit>  $units  the sale unit and the tax units, keyed by code
     *
     * @throws TaxException
     *
     * @return array<string, TaxQuantity> keyed by tax id
     */
    public function forLine(CompiledTaxProfile $profile, string $quantity, ?string $unitCode, Collection $units): array
    {
        $quantities = [];

        foreach ($profile->applicableTaxesPerTaxUnit() as $tax) {
            if ($unitCode === null) {
                throw TaxException::saleUnitRequired($tax->code);
            }

            $saleUnit = $units->get($unitCode) ?? throw TaxException::saleUnitUnavailable($unitCode);

            if ($tax->taxUnitGroupId !== $saleUnit->group_id) {
                throw TaxException::taxUnitIncompatible($tax->code, $saleUnit->group_id);
            }

            $taxUnit = $units->get((string) $tax->taxUnitCode) ?? throw TaxException::taxUnitUnavailable((string) $tax->taxUnitCode);
            $quantities[$tax->id] = new TaxQuantity($quantity, $saleUnit->ratio, $taxUnit->ratio);
        }

        return $quantities;
    }
}
