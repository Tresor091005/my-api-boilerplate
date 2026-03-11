<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class UnitDataDTO extends LahatreDTO
{
    public ?string $id = null;

    public string $name;

    public ?string $symbol = null;

    public ?int $ratio = null;

    protected function casts(): array
    {
        return [
            'ratio' => 'int',
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'id'     => ['nullable', 'uuid'],
            'name'   => ['required', 'string'],
            'symbol' => ['nullable', 'string'],
            'ratio'  => ['nullable', 'integer', 'gt:0'],
        ];
    }
}
