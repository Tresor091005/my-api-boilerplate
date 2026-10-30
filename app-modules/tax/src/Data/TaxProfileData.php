<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

use Illuminate\Support\Collection;

/**
 * Complete profile definition; an update replaces the lines and their overrides.
 */
final readonly class TaxProfileData
{
    /** @param Collection<int, TaxProfileLineData> $lines */
    private function __construct(
        public string $code,
        public string $name,
        public bool $isActive,
        public Collection $lines,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            name: (string) $data['name'],
            isActive: (bool) ($data['is_active'] ?? true),
            lines: collect($data['lines'])->map(TaxProfileLineData::fromArray(...))->values(),
        );
    }
}
