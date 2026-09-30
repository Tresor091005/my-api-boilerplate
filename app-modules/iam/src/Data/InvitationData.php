<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class InvitationData
{
    /** @param list<string> $roleIds */
    private function __construct(public string $email, public array $roleIds) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['email'], $data['role_ids']);
    }
}
