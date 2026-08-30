<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

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
        $read = MissingValueReader::fromArray($data, [
            'stock_tracking_enabled',
            'is_expirable',
            'deduction_strategy',
        ]);
        $stockTrackingEnabled = $read->get('stock_tracking_enabled');
        $isExpirable = $read->get('is_expirable');
        $strategy = $read->get('deduction_strategy');

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
