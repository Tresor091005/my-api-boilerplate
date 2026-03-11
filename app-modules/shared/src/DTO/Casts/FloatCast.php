<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

class FloatCast implements Castable
{
    public function cast(string $key, mixed $value): float
    {
        return (float) $value;
    }
}
