<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class InventoryStockMetadataDTO extends LahatreDTO
{
    public ?array $metadata = null;

    protected function rules(): array
    {
        return [
            'metadata' => ['required', 'nullable', 'array'],
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
