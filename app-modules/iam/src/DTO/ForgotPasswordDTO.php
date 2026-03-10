<?php

declare(strict_types=1);

namespace Lahatre\Iam\DTO;

use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;

class ForgotPasswordDTO extends LahatreDTO
{
    public string $email;

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
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    protected function after(Validator $validator): void
    {
        //
    }
}
