<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions\Auth;

use Lahatre\Shared\Exceptions\AssertionException;

class ResetPasswordFailedException extends AssertionException
{
    public function __construct()
    {
        parent::__construct(
            __('iam::exceptions.auth.password_reset_failed'),
        );
    }
}
