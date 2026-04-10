<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class InventoryStockSummaryFilterDTO extends LahatreDTO
{
    public int $per_page;

    public ?string $cursor = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $item_id = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $location_id = null;

    protected function casts(): array
    {
        return [
            'per_page'    => 'int',
            'cursor'      => 'string',
            'item_id'     => ['string'],
            'location_id' => ['string'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'per_page' => 50,
        ];
    }

    protected function beforeValidation(array $data): array
    {
        foreach (['item_id', 'location_id'] as $key) {
            $value = data_get($data, $key);

            if (is_string($value)) {
                data_set($data, $key, [$value]);
            }
        }

        return $data;
    }

    protected function rules(): array
    {
        return [
            'per_page'      => ['integer', 'min:1', 'max:100'],
            'cursor'        => ['nullable', 'string'],
            'item_id'       => ['nullable', 'array'],
            'item_id.*'     => ['string'],
            'location_id'   => ['nullable', 'array'],
            'location_id.*' => ['string'],
        ];
    }
}
