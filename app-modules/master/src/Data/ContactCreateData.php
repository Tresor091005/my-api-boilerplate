<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Lahatre\Master\Enums\ContactType;

final readonly class ContactCreateData
{
    private function __construct(
        public ContactType $type,
        public string $value,
        public bool $isPrimary,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type: ContactType::from((string) $data['type']),
            value: (string) $data['value'],
            isPrimary: (bool) ($data['is_primary'] ?? false),
        );
    }
}
