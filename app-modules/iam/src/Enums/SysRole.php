<?php

declare(strict_types=1);

namespace Lahatre\Iam\Enums;

enum SysRole: string
{
    case Administrator = 'administrator';

    public function description(): string
    {
        return match ($this) {
            self::Administrator => __('iam::console.roles.administrator.description'),
        };
    }
}
