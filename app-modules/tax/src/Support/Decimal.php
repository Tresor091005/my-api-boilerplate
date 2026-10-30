<?php

declare(strict_types=1);

namespace Lahatre\Tax\Support;

/**
 * Helpers for non-negative decimal strings handled with BCMath.
 */
final class Decimal
{
    public static function places(string $value): int
    {
        $fraction = explode('.', $value, 2)[1] ?? '';

        return strlen(rtrim($fraction, '0'));
    }

    /** "18.500000" → "18.5", "500.000000" → "500". */
    public static function normalize(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
