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
        public ?MovementType $type,
        public string $quantity, // string for BCMath
        public string $unit_code,
        public ?int $unit_cost = null,
        public ?string $currency_code = null,
        public ?CarbonImmutable $expiration_date = null,
        public ?DeductionStrategy $strategy = null,
        public ?array $stock_ids = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            item_id: $data['item_id'],
            location_id: $data['location_id'],
            type: isset($data['type'])
                ? (is_string($data['type']) ? MovementType::from($data['type']) : $data['type'])
                : null,
            quantity: (string) $data['quantity'],
            unit_code: $data['unit_code'],
            unit_cost: isset($data['unit_cost'], $data['currency_code'])
                ? (int) toMinor((string) $data['unit_cost'], $data['currency_code'])
                : ($data['unit_cost'] ?? null),
            currency_code: $data['currency_code'] ?? null,
            expiration_date: isset($data['expiration_date']) ? CarbonImmutable::parse($data['expiration_date']) : null,
            strategy: isset($data['strategy'])
                ? (is_string($data['strategy']) ? DeductionStrategy::from($data['strategy']) : $data['strategy'])
                : null,
            stock_ids: $data['stock_ids'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
