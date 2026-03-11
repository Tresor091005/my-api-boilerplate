<?php

declare(strict_types=1);

namespace Lahatre\Iam\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class ResetPasswordDTO extends LahatreDTO
{
    public string $email;

    public string $token;

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
            'token'    => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'confirmed'],
            // TODO password rules for this
        ];
    }
}
