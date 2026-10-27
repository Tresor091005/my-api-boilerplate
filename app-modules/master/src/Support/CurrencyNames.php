<?php

declare(strict_types=1);

namespace Lahatre\Master\Support;

use Illuminate\Support\Str;
use ResourceBundle;

final class CurrencyNames
{
    /** @var array<string, ResourceBundle|null> */
    private static array $currenciesByLocale = [];

    public static function name(string $code, string $locale): string
    {
        $entry = self::currencies($locale)?->get($code);
        $name = $entry instanceof ResourceBundle ? $entry->get(1) : null;

        return is_string($name) ? $name : $code;
    }

    /**
     * @param  iterable<string>  $codes
     * @return list<string>
     */
    public static function codesStartingWith(iterable $codes, string $prefix, string $locale): array
    {
        $normalizedPrefix = Str::normalize($prefix);
        $matchingCodes = [];

        foreach ($codes as $code) {
            if (Str::startsWith(Str::normalize(self::name($code, $locale)), $normalizedPrefix)) {
                $matchingCodes[] = $code;
            }
        }

        return $matchingCodes;
    }

    private static function currencies(string $locale): ?ResourceBundle
    {
        if (!array_key_exists($locale, self::$currenciesByLocale)) {
            $currencies = ResourceBundle::create($locale, 'ICUDATA-curr')?->get('Currencies');
            self::$currenciesByLocale[$locale] = $currencies instanceof ResourceBundle ? $currencies : null;
        }

        return self::$currenciesByLocale[$locale];
    }
}
