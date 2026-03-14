<?php

declare(strict_types=1);

namespace Lahatre\Master\Support;

use InvalidArgumentException;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

class PreciseConversion
{
    // ========================================
    // CURRENCY — minor units
    // ========================================

    /**
     * 123.45 → 12345
     * Multiply by 10^precision (e.g.: 10^2 = 100 for EUR)
     */
    public static function toMinorUnits(string $amount, Currency $currency): string
    {
        $factor = bcpow('10', (string) $currency->precision, 0);

        return bcmul($amount, $factor, 0);
    }

    /**
     * 12345 → 123.45
     * Divide by 10^precision
     */
    public static function fromMinorUnits(string $minorAmount, Currency $currency): string
    {
        $factor = bcpow('10', (string) $currency->precision, 0);

        return bcdiv($minorAmount, $factor, $currency->precision);
    }

    // ========================================
    // UNITS — ratio-based conversion
    // ========================================

    /** @var int Decimal precision for unit conversions */
    private const UNIT_SCALE = 10;

    /**
     * Converts an amount from one unit to another within the same group.
     *
     * Logic: Each unit has a ratio relative to the group's base unit.
     *   kg → ratio 1000
     *   g  → ratio 1 (base unit)
     *
     * Conversion formula:
     *   amount_to = amount_from * (ratio_from / ratio_to)
     *
     * Example: 10 kg → g: 10 * (1000 / 1) = 10,000 g
     */
    public static function convertUnit(string $amount, Unit $from, Unit $to): string
    {
        if ($from->group_id !== $to->group_id) {
            throw new InvalidArgumentException(
                "Cannot convert {$from->code} to {$to->code}: different unit groups"
            );
        }

        $ratio = bcdiv(
            (string) $from->ratio,
            (string) $to->ratio,
            self::UNIT_SCALE
        );

        return bcmul($amount, $ratio, self::UNIT_SCALE);
    }

    /**
     * Converts an amount to the base unit of the group (ratio = 1).
     *
     * @return array{amount: string, unit: Unit}
     */
    public static function convertUnitToBase(string $amount, Unit $unit): array
    {
        $baseUnit = Unit::where('group_id', $unit->group_id)
            ->where('ratio', 1)
            ->firstOrFail();

        return [
            'amount' => self::convertUnit($amount, $unit, $baseUnit),
            'unit'   => $baseUnit,
        ];
    }

    /**
     * Converts an amount from the base unit of the group to a specific unit.
     *
     * @return array{amount: string, unit: Unit}
     */
    public static function convertUnitFromBase(string $amount, Unit $toUnit): array
    {
        $baseUnit = Unit::where('group_id', $toUnit->group_id)
            ->where('ratio', 1)
            ->firstOrFail();

        return [
            'amount' => self::convertUnit($amount, $baseUnit, $toUnit),
            'unit'   => $toUnit,
        ];
    }
}
