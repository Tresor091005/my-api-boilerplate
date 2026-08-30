<?php

declare(strict_types=1);

namespace Lahatre\Shared\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class LocalTime implements ValidationRule
{
    private const array FORMATS = ['H:i', 'H:i:s'];

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('shared::validation.local_time')->translate();

            return;
        }

        foreach (self::FORMATS as $format) {
            $time = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                $time !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $time->format($format) === $value
            ) {
                return;
            }
        }

        $fail('shared::validation.local_time')->translate();
    }
}
