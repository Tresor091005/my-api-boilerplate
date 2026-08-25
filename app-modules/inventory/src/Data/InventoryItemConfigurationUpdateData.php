<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Shared\Data\MissingValue;

final readonly class InventoryItemConfigurationUpdateData
{
    public function __construct(
        public MissingValue|bool $stockTrackingEnabled,
        public MissingValue|bool $isExpirable,
        public MissingValue|DeductionStrategy|null $deductionStrategy,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $stockTrackingEnabled = array_key_exists('stock_tracking_enabled', $data)
            ? $data['stock_tracking_enabled']
            : MissingValue::Instance;
        $isExpirable = array_key_exists('is_expirable', $data)
            ? $data['is_expirable']
            : MissingValue::Instance;
        $strategy = array_key_exists('deduction_strategy', $data)
            ? $data['deduction_strategy']
            : MissingValue::Instance;

        return new self(
            stockTrackingEnabled: $stockTrackingEnabled,
            isExpirable: $isExpirable,
            deductionStrategy: $strategy instanceof MissingValue || $strategy === null
                ? $strategy
                : DeductionStrategy::from((string) $strategy),
        );
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function toArray(): array
    {
        $values = [
            'stock_tracking_enabled' => $this->stockTrackingEnabled,
            'is_expirable'           => $this->isExpirable,
            'deduction_strategy'     => $this->deductionStrategy instanceof DeductionStrategy
                ? $this->deductionStrategy->value
                : $this->deductionStrategy,
        ];

        return array_filter(
            $values,
            static fn (mixed $value): bool => !$value instanceof MissingValue,
        );
    }
}
