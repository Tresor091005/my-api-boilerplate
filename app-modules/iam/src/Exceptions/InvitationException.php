<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class InvitationException extends AssertionException
{
    public static function unavailable(): self
    {
        return new self(__('iam::exceptions.invitation.unavailable'));
    }

    public static function invalidToken(): self
    {
        return new self(__('iam::exceptions.invitation.invalid_token'));
    }

    public static function alreadyAccepted(): self
    {
        return new self(__('iam::exceptions.invitation.already_accepted'));
    }

    public static function alreadyMember(): self
    {
        return new self(__('iam::exceptions.invitation.already_member'));
    }

    public static function rolesUnavailable(): self
    {
        return new self(__('iam::exceptions.invitation.roles_unavailable'));
    }

    public static function unavailableEmail(): self
    {
        return new self(__('iam::exceptions.invitation.unavailable_email'));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
