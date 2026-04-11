<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class InventoryItemValueFilterDTO extends LahatreDTO
{
    /** @var array<int, string>|null */
    public ?array $location_id = null;

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
            'location_id'     => ['nullable', 'array', 'min:1'],
            'location_id.*'   => ['string'],
            'currency_code'   => ['nullable', 'array', 'min:1'],
            'currency_code.*' => ['string', 'size:3'],
        ];
    }
}
