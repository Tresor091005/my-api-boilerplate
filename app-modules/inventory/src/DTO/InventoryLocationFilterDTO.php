<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Illuminate\Validation\Rule;
use Lahatre\Shared\DTO\LahatreDTO;

class InventoryLocationFilterDTO extends LahatreDTO
{
    public int $per_page;

    public ?string $cursor = null;

    public string $sort_by;

    public string $sort_order;

    /** @var array<int, string>|null */
    public ?array $ids = null;

    public ?string $external_type = null;

    /** @var array<int, string>|null */
    public ?array $external_id = null;

    public ?bool $is_active = null;

    protected function casts(): array
    {
        return [
            'per_page'  => 'int',
            'is_active' => 'bool',
        ];
    }

    protected function defaults(): array
    {
        return [
            'per_page'   => 15,
            'sort_by'    => 'id',
            'sort_order' => 'asc',
        ];
    }

    protected function rules(): array
    {
        return [
            'per_page'      => ['integer', 'min:1', 'max:100'],
            'cursor'        => ['nullable', 'string'],
            'sort_by'       => ['string', Rule::in(['id', 'external_type', 'external_id', 'created_at', 'updated_at'])],
            'sort_order'    => ['string', Rule::in(['asc', 'desc'])],
            'ids'           => ['nullable', 'array', 'min:1'],
            'ids.*'         => ['string'],
            'external_type' => ['required_with:external_id', 'string'],
            'external_id'   => ['required_with:external_type', 'array', 'min:1'],
            'external_id.*' => ['string'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }
}
