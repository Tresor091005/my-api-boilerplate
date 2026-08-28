<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Carbon\CarbonImmutable;
use Lahatre\Inventory\Enums\DeductionStrategy;

final readonly class InventoryLotFilterData
{
    private function __construct(
        public ?DeductionStrategy $strategy,
        public ?CarbonImmutable $expiringBefore,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $strategy = $data['strategy'] ?? null;

        return new self(
            strategy: is_string($strategy) ? DeductionStrategy::from($strategy) : $strategy,
            expiringBefore: isset($data['expiring_before'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', $data['expiring_before'])
                : null,
        );
    }
}
