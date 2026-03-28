<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Validation\Rule;
use Lahatre\Shared\DTO\LahatreDTO;

class OptionValueFilterDTO extends LahatreDTO
{
    public int $per_page;

    public ?string $cursor = null;

    public string $sort_by;

    public string $sort_order;

    public ?string $value = null;

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
            'sort_by'    => 'value',
            'sort_order' => 'asc',
        ];
    }

    protected function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['value', 'created_at', 'updated_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'value'      => ['nullable', 'string', 'max:255'],
        ];
    }
}
