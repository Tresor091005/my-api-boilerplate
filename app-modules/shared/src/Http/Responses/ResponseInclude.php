<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Responses;

final readonly class ResponseInclude
{
    /** @param list<string> $loads */
    public function __construct(
        public string $name,
        public array $loads = [],
    ) {}

    public static function fromArray(string $name, mixed $definition): self
    {
        return new self(
            name: $name,
            loads: is_array($definition) ? array_values($definition['loads'] ?? []) : [$name],
        );
    }
}
