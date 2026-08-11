<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class ProductVariantData
{
    /**
     * @param  array<int, array{name: string, value: string}>  $options
     */
    private function __construct(
        public ?string $sku,
        public string $unitGroupId,
        public bool $shouldManageStock,
        public bool $isActive,
        public array $options,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'] ?? null,
            unitGroupId: $data['unit_group_id'],
            shouldManageStock: (bool) ($data['should_manage_stock'] ?? false),
            isActive: (bool) ($data['is_active'] ?? false),
            options: $data['options'],
        );
    }
}
