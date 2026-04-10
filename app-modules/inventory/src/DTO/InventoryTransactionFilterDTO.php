<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Illuminate\Validation\Rule;
use Lahatre\Shared\DTO\LahatreDTO;

class InventoryTransactionFilterDTO extends LahatreDTO
{
    public int $per_page;

    public ?string $cursor = null;

    public string $sort_by;

    public string $sort_order;

    /** @var array<int, string>|null */
    public ?array $ids = null;

    public ?string $reference_type = null;

    /** @var array<int, string>|null */
    public ?array $reference_id = null;

    public ?string $transaction_type = null;

    protected function casts(): array
    {
        return [
            'per_page' => 'int',
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
            'per_page'         => ['integer', 'min:1', 'max:100'],
            'cursor'           => ['nullable', 'string'],
            'sort_by'          => ['string', Rule::in(['id', 'reference_type', 'reference_id', 'transaction_type', 'created_at', 'updated_at'])],
            'sort_order'       => ['string', Rule::in(['asc', 'desc'])],
            'ids'              => ['nullable', 'array', 'min:1'],
            'ids.*'            => ['string'],
            'reference_type'   => ['required_with:reference_id', 'string'],
            'reference_id'     => ['required_with:reference_type', 'array', 'min:1'],
            'reference_id.*'   => ['string'],
            'transaction_type' => ['nullable', 'string'],
        ];
    }
}
