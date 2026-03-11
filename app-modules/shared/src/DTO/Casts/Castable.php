<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

interface Castable
{
    /**
     * Cast the given value to the target type.
     */
    public function cast(string $key, mixed $value): mixed;
}
