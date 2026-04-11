<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class InventoryStockExpiringFilterDTO extends LahatreDTO
{
    public int $per_page;

    public ?string $cursor = null;

    public int $days;

    public ?string $location_id = null;

    public ?string $item_id = null;

    protected function casts(): array
    {
        return [
            'per_page' => 'int',
            'cursor'   => 'string',
            'days'     => 'int',
        ];
    }

    protected function defaults(): array
    {
        return [
            'per_page' => 50,
            'days'     => 7,
        ];
    }

    protected function rules(): array
    {
        return [
            'per_page'    => ['integer', 'min:1', 'max:100'],
            'cursor'      => ['nullable', 'string'],
            'days'        => ['integer', 'min:1', 'max:365'],
            'location_id' => ['nullable', 'string'],
            'item_id'     => ['nullable', 'string'],
        ];
    }
}
