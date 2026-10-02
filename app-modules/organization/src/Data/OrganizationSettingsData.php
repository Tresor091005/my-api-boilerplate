<?php

declare(strict_types=1);

namespace Lahatre\Organization\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class OrganizationSettingsData
{
    /** @param MissingValue|array<int, string> $enableCurrencies */
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|array $enableCurrencies,
        public MissingValue|string $timezone,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $read = MissingValueReader::fromArray($data, ['name', 'enable_currencies', 'timezone']);

        return new self($read->get('name'), $read->get('enable_currencies'), $read->get('timezone'));
    }
}
