<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class MemberRoleException extends AssertionException
{
    public static function defaultUnavailable(): self
    {
        return new self(__('iam::exceptions.member_role.default_unavailable'));
    }

    public static function rolesUnavailable(): self
    {
        return new self(__('iam::exceptions.member_role.roles_unavailable'));
    }

    public static function alreadyAssigned(): self
    {
        return new self(__('iam::exceptions.member_role.already_assigned'));
    }

    public static function assignmentsUnavailable(): self
    {
        return new self(__('iam::exceptions.member_role.assignments_unavailable'));
    }

    public static function ownerAdministratorProtected(): self
    {
        return new self(__('iam::exceptions.member_role.owner_administrator_protected'));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
