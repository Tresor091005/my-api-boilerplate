<?php

declare(strict_types=1);

namespace Lahatre\Master\Contracts;

use Illuminate\Support\Collection;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

interface MasterInterface
{
    /**
     * @param  Collection<int, string>  $codes
     * @return Collection<string, Currency>
     */
    public function currencies(Collection $codes): Collection;

    public function currency(string $code): Currency;

    public function fromMinor(string $amount, string $currencyCode): string;

    public function toMinor(string $minorAmount, string $currencyCode): string;

    /**
     * @param  Collection<int, string>  $codes
     * @return Collection<string, Unit>
     */
    public function units(Collection $codes): Collection;

    public function unit(string $code): Unit;

    public function baseUnit(string $unitGroupId): Unit;

    public function convertUnit(string $amount, string $fromCode, string $toCode): string;

    public function convertUnitToBase(string $amount, string $fromCode): array;

    public function convertUnitFromBase(string $amount, string $toCode): array;
}
