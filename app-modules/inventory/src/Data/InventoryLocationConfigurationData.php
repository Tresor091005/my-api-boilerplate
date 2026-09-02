<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryLocationConfigurationData
{
    public function __construct(
        public bool $isActive = true,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
