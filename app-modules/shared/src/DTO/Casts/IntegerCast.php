<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

class IntegerCast implements Castable
{
    public function cast(string $key, mixed $value): int
    {
        return (int) $value;
    }
}
