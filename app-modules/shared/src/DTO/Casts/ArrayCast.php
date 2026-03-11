<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

class ArrayCast implements Castable
{
    public function __construct(protected ?Castable $subCast = null) {}

    public function cast(string $key, mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        } else {
            $value = is_array($value) ? $value : [$value];
        }

        if ($this->subCast) {
            return array_map(fn ($item) => $this->subCast->cast($key, $item), $value);
        }

        return $value;
    }
}
