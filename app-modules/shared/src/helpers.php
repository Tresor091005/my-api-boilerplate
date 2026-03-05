<?php

declare(strict_types=1);
use Lahatre\Catalog\Models\Currency;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Iam\Auth\AuthContext;
use Lahatre\Shared\Support\PreciseConversion;

if (!function_exists('authContext')) {
    function authContext(): AuthContext
    {
        return app(AuthContext::class);
    }
}

if (!function_exists('getDefaultTeamId')) {
    function getDefaultTeamId(): string
    {
        // TODO: SHOULD BE REMOVED when authContext is complete
        return '019c5b9b-697d-72e5-ab19-b2186fc49375';
    }
}

/* -----------------------------------------------------------------
 | Currencies
 | -----------------------------------------------------------------
 */

if (!function_exists('currency')) {
    function currency(string $code): Currency
    {
        return Currency::where('code', $code)->firstOrFail();
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
        return Unit::where('code', $code)->firstOrFail();
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
