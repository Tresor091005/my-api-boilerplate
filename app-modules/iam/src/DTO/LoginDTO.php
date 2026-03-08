<?php

declare(strict_types=1);

namespace Lahatre\Iam\DTO;

use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;

class LoginDTO extends LahatreDTO
{
    public string $email;

    public string $password;

    public string $type;

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
            'type'     => ['required', 'string', 'in:user,company-member'],
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function after(Validator $validator): void
    {
        //
    }
}
