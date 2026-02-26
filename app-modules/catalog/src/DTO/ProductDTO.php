<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;

class ProductDTO extends LahatreDTO
{
    protected function casts(): array
    {
        return [];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function beforeValidation(array $data): array
    {
        return $data;
    }

    protected function rules(): array
    {
        return [];
    }

    protected function after(Validator $validator): void
    {
        //
    }
}
