<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Lahatre\Inventory\Enums\DeductionStrategy;

final readonly class InventoryItemConfigurationData
{
    public function __construct(
        public bool $stockTrackingEnabled = true,
        public bool $isExpirable = false,
        public ?DeductionStrategy $deductionStrategy = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $strategy = $data['deduction_strategy'] ?? null;

        return new self(
            stockTrackingEnabled: (bool) ($data['stock_tracking_enabled'] ?? true),
            isExpirable: (bool) ($data['is_expirable'] ?? false),
            deductionStrategy: $strategy === null ? null : DeductionStrategy::from((string) $strategy),
        );
    }
}
