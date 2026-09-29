<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class RoleException extends AssertionException
{
    public static function unavailable(string $roleId): self
    {
        return new self(__('iam::exceptions.role.unavailable'), ['role_id' => $roleId]);
    }

    public static function systemRole(string $roleId): self
    {
        return new self(__('iam::exceptions.role.system_role'), ['role_id' => $roleId]);
    }

    public static function nameTaken(string $name): self
    {
        return new self(__('iam::exceptions.role.name_taken'), ['name' => $name]);
    }

    public static function permissionsUnavailable(): self
    {
        return new self(__('iam::exceptions.role.permissions_unavailable'));
    }

    public static function assigned(string $roleId): self
    {
        return new self(__('iam::exceptions.role.assigned'), ['role_id' => $roleId]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
