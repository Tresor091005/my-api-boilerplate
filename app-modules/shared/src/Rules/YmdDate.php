<?php

declare(strict_types=1);

namespace Lahatre\Shared\Rules;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class YmdDate implements ValidationRule
{
    private const string FORMAT = 'Y-m-d';

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof DateTimeInterface) {
            return;
        }

        if (!is_string($value)) {
            $fail('shared::validation.ymd_date')->translate();

            return;
        }

        $date = DateTimeImmutable::createFromFormat('!'.self::FORMAT, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format(self::FORMAT) !== $value
        ) {
            $fail('shared::validation.ymd_date')->translate();
        }
    }
}
