<?php

declare(strict_types=1);

namespace Lahatre\Iam\DTO;

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

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }
}
