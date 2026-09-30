<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class MemberRoleCreateData
{
    /** @param list<string> $roleIds */
    private function __construct(public array $roleIds, public bool $isActive) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['role_ids'], (bool) ($data['is_active'] ?? true));
    }
}
