<?php

declare(strict_types=1);

namespace Lahatre\Shared\Rules;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class Rfc3339Utc implements ValidationRule
{
    private const string FORMAT_WITHOUT_MILLISECONDS_Z = 'Y-m-d\\TH:i:s\\Z';

    private const string FORMAT_WITHOUT_MILLISECONDS_OFFSET = 'Y-m-d\\TH:i:sP';

    private const string FORMAT_WITH_MILLISECONDS_Z = 'Y-m-d\\TH:i:s.v\\Z';

    private const string FORMAT_WITH_MILLISECONDS_OFFSET = 'Y-m-d\\TH:i:s.vP';

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('shared::validation.rfc3339_utc')->translate();

            return;
        }

        $hasMilliseconds = str_contains($value, '.');
        $hasZuluSuffix = str_ends_with($value, 'Z');
        $format = match (true) {
            $hasMilliseconds && $hasZuluSuffix => self::FORMAT_WITH_MILLISECONDS_Z,
            $hasMilliseconds                   => self::FORMAT_WITH_MILLISECONDS_OFFSET,
            $hasZuluSuffix                     => self::FORMAT_WITHOUT_MILLISECONDS_Z,
            default                            => self::FORMAT_WITHOUT_MILLISECONDS_OFFSET,
        };

        if (!$hasZuluSuffix && !str_ends_with($value, '+00:00')) {
            $fail('shared::validation.rfc3339_utc')->translate();

            return;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!'.$format,
            $value,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format($format) !== $value
        ) {
            $fail('shared::validation.rfc3339_utc')->translate();
        }
    }
}
