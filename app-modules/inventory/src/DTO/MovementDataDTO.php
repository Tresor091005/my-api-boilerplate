<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Carbon\CarbonImmutable;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;

readonly class MovementDataDTO
{
    public function __construct(
        public string $item_id,
        public string $location_id,
        public MovementType $type,
        public string $quantity, // string for BCMath
        public string $unit_code,
        public ?int $unit_cost,
        public ?string $currency_code,
        public ?CarbonImmutable $peremption_date = null,
        public ?DeductionStrategy $strategy = null,
        public ?array $stock_ids = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            item_id: $data['item_id'],
            location_id: $data['location_id'],
            type: is_string($data['type'])
                ? MovementType::from($data['type'])
                : $data['type'],
            quantity: (string) $data['quantity'],
            unit_code: $data['unit_code'] ?? null,
            unit_cost: $data['unit_cost'] ?? null,
            currency_code: $data['currency_code'],
            peremption_date: isset($data['peremption_date']) ? CarbonImmutable::parse($data['peremption_date']) : null,
            strategy: isset($data['strategy'])
                ? (is_string($data['strategy']) ? DeductionStrategy::from($data['strategy']) : $data['strategy'])
                : null,
            stock_ids: $data['stock_ids'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
