<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

class DTOCast implements Castable
{
    public function __construct(protected string $dtoClass) {}

    public function cast(string $key, mixed $value): mixed
    {
        if (is_null($value)) return null;
        if ($value instanceof $this->dtoClass) return $value;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return new $this->dtoClass(is_array($value) ? $value : []);
    }
}
