<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class OrganizationOnboardingException extends AssertionException
{
    public static function systemRolesUnavailable(): self
    {
        return new self(__('iam::exceptions.organization_onboarding.system_roles_unavailable'));
    }

    public static function invalidRegistrationToken(): self
    {
        return new self(__('iam::exceptions.organization_onboarding.invalid_registration_token'));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
