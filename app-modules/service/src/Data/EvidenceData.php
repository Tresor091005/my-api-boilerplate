<?php

declare(strict_types=1);

namespace Lahatre\Service\Data;

use Carbon\CarbonImmutable;

final readonly class EvidenceData
{
    /** @param array<string, mixed> $snapshot */
    public function __construct(
        public array $snapshot,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
