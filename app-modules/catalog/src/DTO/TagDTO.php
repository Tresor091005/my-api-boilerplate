<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;

class TagDTO extends LahatreDTO
{
    public string $name;

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
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    protected function after(Validator $validator): void
    {
        //
    }
}
