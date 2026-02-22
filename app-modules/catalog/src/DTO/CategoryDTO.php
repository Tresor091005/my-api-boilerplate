<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;

class CategoryDTO extends LahatreDTO
{
    public string $name;

    public ?string $parent_id = null;

    public bool $is_active;

    protected function casts(): array
    {
        return [
            'is_active' => new BooleanCast(),
        ];
    }

    protected function defaults(): array
    {
        return [
            'is_active' => false,
        ];
    }

    protected function beforeValidation(array $data): array
    {
        return $data;
    }

    protected function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'string', 'exists:catalog_categories,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function after(Validator $validator): void
    {
        //
    }
}
