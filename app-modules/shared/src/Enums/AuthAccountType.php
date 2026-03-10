<?php

declare(strict_types=1);

namespace Lahatre\Shared\Enums;

use App\Models\Company\CompanyMember;
use App\Models\User\User;

enum AuthAccountType: string
{
    case User = 'user';
    case CompanyMember = 'company-member';

    public function model(): string
    {
        return match ($this) {
            self::User          => User::class,
            self::CompanyMember => CompanyMember::class,
        };
    }

    public function authProvider(): string
    {
        return match ($this) {
            self::User          => 'users',
            self::CompanyMember => 'company-members',
        };
    }

    // TODO : Problème à corriger, utiliser peut être une interface
}
