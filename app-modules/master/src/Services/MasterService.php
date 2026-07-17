<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Support\Collection;
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

    /**
     * @param  Collection<int, string>  $codes
     * @return Collection<string, Currency>
     */
    public function currencies(Collection $codes): Collection
    {
        return $this->unitCache->getCurrenciesByCodes($codes);
    }

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

    /**
     * @param  Collection<int, string>  $codes
     * @return Collection<string, Unit>
     */
    public function units(Collection $codes): Collection
    {
        return $this->unitCache->getByCodes($codes);
    }

    public function unit(string $code): Unit
    {
        return $this->unitCache->getByCode($code);
    }

    public function baseUnit(string $unitGroupId): Unit
    {
        return $this->unitCache->getBaseUnit($unitGroupId);
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
