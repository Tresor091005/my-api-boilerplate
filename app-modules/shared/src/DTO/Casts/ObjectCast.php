<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

class ObjectCast implements Castable
{
    public function cast(string $key, mixed $value): ?object
    {
        if (is_null($value)) return null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return (object) $value;
    }
}
