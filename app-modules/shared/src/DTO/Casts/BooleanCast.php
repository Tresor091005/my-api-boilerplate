<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

class BooleanCast implements Castable
{
    public function cast(string $key, mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }
        if (is_numeric($value)) {
            return $value > 0;
        }
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }
}
