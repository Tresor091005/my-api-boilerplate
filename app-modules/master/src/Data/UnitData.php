<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class UnitData
{
    private function __construct(
        public ?string $id,
        public string $name,
        public ?string $symbol,
        public ?int $ratio,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            symbol: $data['symbol'] ?? null,
            ratio: array_key_exists('ratio', $data) && $data['ratio'] !== null ? (int) $data['ratio'] : null,
        );
    }
}
