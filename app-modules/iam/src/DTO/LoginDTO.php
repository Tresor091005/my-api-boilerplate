<?php

declare(strict_types=1);

namespace Lahatre\Iam\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class LoginDTO extends LahatreDTO
{
    public string $email;

    public string $password;

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
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
