<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class InventoryStockExpiringFilterDTO extends LahatreDTO
{
    public int $page;

    public int $per_page;

    public int $days;

    public ?string $location_id = null;

    protected function casts(): array
    {
        return [
            'page'     => 'int',
            'per_page' => 'int',
            'days'     => 'int',
        ];
    }

    protected function defaults(): array
    {
        return [
            'page'     => 1,
            'per_page' => 50,
            'days'     => 7,
        ];
    }

    protected function rules(): array
    {
        return [
            'page'        => ['integer', 'min:1'],
            'per_page'    => ['integer', 'min:1', 'max:100'],
            'days'        => ['integer', 'min:1', 'max:365'],
            'location_id' => ['nullable', 'string'],
        ];
    }
}
