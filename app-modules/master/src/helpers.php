<?php

declare(strict_types=1);

use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Support\PreciseConversion;
use Lahatre\Master\Support\UnitCache;

/* -----------------------------------------------------------------
 | Currencies
 | -----------------------------------------------------------------
 */

if (!function_exists('currency')) {
    function currency(string $code): Currency
    {
        return app(UnitCache::class)->getCurrencyByCode($code);
    }
}

if (!function_exists('fromMinor')) {
    function fromMinor(string $amount, string $currencyCode): string
    {
        return PreciseConversion::fromMinorUnits($amount, currency($currencyCode));
    }
}

if (!function_exists('toMinor')) {
    function toMinor(string $minorAmount, string $currencyCode): string
    {
        return PreciseConversion::toMinorUnits($minorAmount, currency($currencyCode));
    }
}

/* -----------------------------------------------------------------
 | Units
 | -----------------------------------------------------------------
 */

if (!function_exists('unit')) {
    function unit(string $code): Unit
    {
        return app(UnitCache::class)->getByCode($code);
    }
}

if (!function_exists('convertUnit')) {
    function convertUnit(string $amount, string $fromCode, string $toCode): string
    {
        return PreciseConversion::convertUnit($amount, unit($fromCode), unit($toCode));
    }
}

if (!function_exists('convertUnitToBase')) {
    function convertUnitToBase(string $amount, string $fromCode): array
    {
        return PreciseConversion::convertUnitToBase($amount, unit($fromCode));
    }
}

if (!function_exists('convertUnitFromBase')) {
    function convertUnitFromBase(string $amount, string $toCode): array
    {
        return PreciseConversion::convertUnitFromBase($amount, unit($toCode));
    }
}
