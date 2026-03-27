<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Support\PreciseConversion;
use Lahatre\Master\Support\UnitCache;

class MasterService implements MasterInterface
{
    public function __construct(
        protected UnitCache $unitCache
    ) {}

    public function currency(string $code): Currency
    {
        return $this->unitCache->getCurrencyByCode($code);
    }

    public function fromMinor(string $amount, string $currencyCode): string
    {
        return PreciseConversion::fromMinorUnits($amount, $this->currency($currencyCode));
    }

    public function toMinor(string $minorAmount, string $currencyCode): string
    {
        return PreciseConversion::toMinorUnits($minorAmount, $this->currency($currencyCode));
    }

    public function unit(string $code): Unit
    {
        return $this->unitCache->getByCode($code);
    }

    public function convertUnit(string $amount, string $fromCode, string $toCode): string
    {
        return PreciseConversion::convertUnit($amount, $this->unit($fromCode), $this->unit($toCode));
    }

    public function convertUnitToBase(string $amount, string $fromCode): array
    {
        return PreciseConversion::convertUnitToBase($amount, $this->unit($fromCode));
    }

    public function convertUnitFromBase(string $amount, string $toCode): array
    {
        return PreciseConversion::convertUnitFromBase($amount, $this->unit($toCode));
    }
}
