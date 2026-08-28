<?php

declare(strict_types=1);

namespace Lahatre\Shared\Rules;

use Closure;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class IanaTimezone implements ValidationRule
{
    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !in_array($value, DateTimeZone::listIdentifiers(), true)) {
            $fail('shared::validation.iana_timezone')->translate();
        }
    }
}
