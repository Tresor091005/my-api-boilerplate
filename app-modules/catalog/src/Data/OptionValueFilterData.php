<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class OptionValueFilterData
{
    private function __construct(
        public string $sortBy,
        public string $sortOrder,
        public ?string $value,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sortBy: $data['sort_by'] ?? 'value',
            sortOrder: $data['sort_order'] ?? 'asc',
            value: $data['value'] ?? null,
        );
    }
}
