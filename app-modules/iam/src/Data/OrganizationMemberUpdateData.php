<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class OrganizationMemberUpdateData
{
    private function __construct(public bool $isActive) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self((bool) $data['is_active']);
    }
}
