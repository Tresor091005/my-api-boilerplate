<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class InventoryLocationValueFilterDTO extends LahatreDTO
{
    /** @var array<int, string>|null */
    public ?array $item_id = null;

    /** @var array<int, string>|null */
    public ?array $currency_code = null;

    protected function casts(): array
    {
        return [];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'item_id'         => ['nullable', 'array', 'min:1'],
            'item_id.*'       => ['string'],
            'currency_code'   => ['nullable', 'array', 'min:1'],
            'currency_code.*' => ['string', 'size:3'],
        ];
    }
}
