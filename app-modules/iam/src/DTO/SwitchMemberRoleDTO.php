<?php

declare(strict_types=1);

namespace Lahatre\Iam\DTO;

use Lahatre\Shared\DTO\LahatreDTO;

class SwitchMemberRoleDTO extends LahatreDTO
{
    public string $member_role_id;

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
            'member_role_id' => ['required', 'string', 'uuid'],
        ];
    }
}
