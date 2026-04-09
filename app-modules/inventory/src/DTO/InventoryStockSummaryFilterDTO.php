<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class InventoryStockSummaryFilterDTO extends LahatreDTO
{
    public int $page;

    public int $per_page;

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
            'page'        => 'int',
            'per_page'    => 'int',
            'item_id'     => ['string'],
            'location_id' => ['string'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'page'     => 1,
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
            'page'          => ['integer', 'min:1'],
            'per_page'      => ['integer', 'min:1', 'max:100'],
            'item_id'       => ['nullable', 'array'],
            'item_id.*'     => ['string'],
            'location_id'   => ['nullable', 'array'],
            'location_id.*' => ['string'],
        ];
    }
}
