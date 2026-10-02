<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class EmailAccountException extends AssertionException
{
    public static function unavailableEmail(): self
    {
        return new self(__('iam::exceptions.email_account.unavailable_email'));
    }

    public static function profileIncomplete(): self
    {
        return new self(__('iam::exceptions.auth.profile_incomplete'));
    }

    public static function userDetailsForbidden(): self
    {
        return new self(__('iam::exceptions.email_account.user_details_forbidden'));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
