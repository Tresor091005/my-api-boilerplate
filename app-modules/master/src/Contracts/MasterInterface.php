<?php

declare(strict_types=1);

namespace Lahatre\Master\Contracts;

use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

interface MasterInterface
{
    public function currency(string $code): Currency;

    public function fromMinor(string $amount, string $currencyCode): string;

    public function toMinor(string $minorAmount, string $currencyCode): string;

    public function unit(string $code): Unit;

    public function convertUnit(string $amount, string $fromCode, string $toCode): string;

    public function convertUnitToBase(string $amount, string $fromCode): array;

    public function convertUnitFromBase(string $amount, string $toCode): array;
}
