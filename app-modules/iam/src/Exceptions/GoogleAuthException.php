<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class GoogleAuthException extends AssertionException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function configurationMissing(): self
    {
        return new self(__('iam::exceptions.google.configuration_missing'));
    }

    public static function invalidCredential(): self
    {
        return new self(__('iam::exceptions.google.invalid_credential'));
    }

    public static function invalidChallenge(): self
    {
        return new self(__('iam::exceptions.google.invalid_challenge'));
    }

    public static function identityUnavailable(): self
    {
        return new self(__('iam::exceptions.google.identity_unavailable'));
    }

    public static function recentEmailAuthenticationRequired(): self
    {
        return new self(__('iam::exceptions.google.recent_email_authentication_required'));
    }

    public static function invalidOrigin(): self
    {
        return new self(__('iam::exceptions.google.invalid_origin'));
    }

    public static function verificationUnavailable(): self
    {
        return new self(__('iam::exceptions.google.verification_unavailable'));
    }
}
