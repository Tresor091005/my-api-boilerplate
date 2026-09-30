<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class MemberRoleDeleteData
{
    /** @param list<string> $memberRoleIds */
    private function __construct(public array $memberRoleIds) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['member_role_ids']);
    }
}
