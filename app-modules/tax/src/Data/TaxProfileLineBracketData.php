<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

final readonly class TaxProfileLineBracketData
{
    private function __construct(
        public string $taxBracketId,
        public string $value,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxBracketId: (string) $data['tax_bracket_id'],
            value: (string) $data['value'],
        );
    }
}
