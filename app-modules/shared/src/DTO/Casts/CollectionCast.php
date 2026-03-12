<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

use Illuminate\Support\Collection;

class CollectionCast implements Castable
{
    public function __construct(private readonly ?Castable $type = null) {}

    public function cast(string $key, mixed $value): Collection
    {
        $array = (new ArrayCast())->cast($key, $value);

        return collect($array)
            ->when(
                $this->type,
                fn ($col) => $col->map(fn ($item): mixed => $this->type->cast($key, $item))
            );
    }
}
