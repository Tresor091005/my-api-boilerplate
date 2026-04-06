<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Validation\Rule;
use Lahatre\Shared\DTO\LahatreDTO;

class ProductVariantFilterDTO extends LahatreDTO
{
    public int $per_page;

    public ?string $cursor = null;

    public string $sort_by;

    public string $sort_order;

    public ?bool $should_manage_stock = null;

    public ?bool $is_active = null;

    protected function casts(): array
    {
        return [
            'per_page'            => 'int',
            'should_manage_stock' => 'bool',
            'is_active'           => 'bool',
        ];
    }

    protected function defaults(): array
    {
        return [
            'per_page'   => 15,
            'sort_by'    => 'created_at',
            'sort_order' => 'asc',
        ];
    }

    protected function rules(): array
    {
        return [
            'per_page'            => ['integer', 'min:1', 'max:100'],
            'cursor'              => ['nullable', 'string'],
            'sort_by'             => ['string', Rule::in(['sku', 'should_manage_stock', 'is_active', 'created_at', 'updated_at'])],
            'sort_order'          => ['string', Rule::in(['asc', 'desc'])],
            'should_manage_stock' => ['nullable', 'boolean'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }
}
