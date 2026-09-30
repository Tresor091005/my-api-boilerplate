<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class MemberRoleUpdateData
{
    /** @param list<string> $memberRoleIds */
    private function __construct(public array $memberRoleIds, public bool $isActive) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['member_role_ids'], (bool) $data['is_active']);
    }
}
