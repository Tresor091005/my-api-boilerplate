<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class ServiceDeliverableTemplateData
{
    private function __construct(
        public MissingValue|string $id,
        public string $name,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $read = MissingValueReader::fromArray($data, ['id']);

        return new self(
            id: $read->get('id'),
            name: $read->get('name'),
        );
    }
}
