<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Support\Str;

class SkuGenerator
{
    /**
     * Generates a unique-ish, readable SKU.
     * Format: PREFIX-YYMMDD-RANDOM (ex: IPHONE-15-260308-X8YZ)
     */
    public static function generate(string $source): string
    {
        $prefix = str($source)
            ->slug()
            ->limit(10, '')
            ->trim('-')
            ->toString();

        $date = now()->format('ymd');
        $random = Str::random(4);

        return Str::upper("{$prefix}-{$date}-{$random}");
    }
}
