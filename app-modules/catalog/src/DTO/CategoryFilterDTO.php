<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Validation\Rule;
use Lahatre\Shared\DTO\LahatreDTO;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;

class CategoryFilterDTO extends LahatreDTO
{
    public int $per_page;

    public ?string $cursor = null;

    public string $sort_by;

    public string $sort_order;

    public ?string $handle = null;

    public ?string $name = null;

    public ?bool $is_active = null;

    public ?string $parent_id = null;

    protected function casts(): array
    {
        return [
            'per_page'  => new IntegerCast(),
            'is_active' => new BooleanCast(),
        ];
    }

    protected function defaults(): array
    {
        return [
            'per_page'   => 15,
            'sort_by'    => 'handle',
            'sort_order' => 'asc',
        ];
    }

    protected function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['handle', 'name', 'created_at', 'updated_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'handle'     => ['nullable', 'string', 'max:255'],
            'name'       => ['nullable', 'string', 'max:255'],
            'is_active'  => ['nullable', 'boolean'],
            'parent_id'  => ['nullable', 'string'],
        ];
    }

    // TODO generation command
}
