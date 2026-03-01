<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class UnitDataDTO extends ValidatedDTO
{
    public ?string $id = null;

    public string $name;

    public ?string $symbol = null;

    public ?int $ratio = null;

    public ?bool $is_active = null;

    protected function casts(): array
    {
        return [
            'ratio'     => new IntegerCast(),
            'is_active' => new BooleanCast(),
        ];
    }

    protected function defaults(): array
    {
        return [
            'is_active' => true,
        ];
    }

    protected function rules(): array
    {
        return [
            'id'        => ['nullable', 'uuid'],
            'name'      => ['required', 'string'],
            'symbol'    => ['nullable', 'string'],
            'ratio'     => ['nullable', 'integer', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
