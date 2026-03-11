<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

class StringCast implements Castable
{
    public function cast(string $key, mixed $value): string
    {
        return (string) $value;
    }
}
