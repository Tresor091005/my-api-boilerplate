<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class UserUpdateData
{
    private function __construct(
        public MissingValue|string $firstName,
        public MissingValue|string $lastName,
        public MissingValue|string|null $defaultMemberRoleId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $read = MissingValueReader::fromArray($data, ['first_name', 'last_name', 'default_member_role_id']);

        return new self($read->get('first_name'), $read->get('last_name'), $read->get('default_member_role_id'));
    }
}
