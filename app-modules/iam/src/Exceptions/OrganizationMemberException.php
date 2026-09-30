<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class OrganizationMemberException extends AssertionException
{
    public static function unavailable(): self
    {
        return new self(__('iam::exceptions.organization_member.unavailable'));
    }

    public static function owner(): self
    {
        return new self(__('iam::exceptions.organization_member.owner'));
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }
}
