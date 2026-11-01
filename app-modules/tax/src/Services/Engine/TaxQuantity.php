<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Lahatre\Tax\Support\Decimal;

/**
 * A sold quantity read in the unit of a per-unit tax. The unit ratios are kept
 * so a charge multiplies before it divides and no precision is lost.
 */
final readonly class TaxQuantity
{
    /** Wide enough for a rate (6 decimals) times a quantity (10 decimals). */
    private const int PRODUCT_SCALE = 20;

    public function __construct(
        public string $soldQuantity,
        public int $soldUnitRatio,
        public int $taxUnitRatio,
    ) {}

    /** The quantity expressed in the tax unit, for display. */
    public function inTaxUnit(int $scale): string
    {
        return Decimal::normalize(bcdiv(
            bcmul($this->soldQuantity, (string) $this->soldUnitRatio, self::PRODUCT_SCALE),
            (string) $this->taxUnitRatio,
            $scale,
        ));
    }

    /** rate × quantity × sold unit ratio / tax unit ratio. */
    public function charge(string $ratePerTaxUnit, int $scale): string
    {
        $product = bcmul(
            bcmul($ratePerTaxUnit, $this->soldQuantity, self::PRODUCT_SCALE),
            (string) $this->soldUnitRatio,
            self::PRODUCT_SCALE,
        );

        // Keep the full precision: rounding 'up' must still see the smallest remainder.
        return bcdiv($product, (string) $this->taxUnitRatio, max($scale, self::PRODUCT_SCALE));
    }
}
