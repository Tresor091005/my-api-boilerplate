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

    public static function unavailableEmail(): self
    {
        return new self(__('iam::exceptions.organization_onboarding.unavailable_email'));
    }

    public static function userDetailsRequired(): self
    {
        return new self(__('iam::exceptions.organization_onboarding.user_details_required'));
    }

    public static function userDetailsForbidden(): self
    {
        return new self(__('iam::exceptions.organization_onboarding.user_details_forbidden'));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
