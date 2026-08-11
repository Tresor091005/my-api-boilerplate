<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Carbon\CarbonImmutable;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Master\Contracts\MasterInterface;

final readonly class MovementData
{
    /**
     * @param  array<int, string>|null  $stockIds
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $stockMetadata
     */
    private function __construct(
        public string $itemId,
        public string $locationId,
        public ?string $toLocationId,
        public ?MovementType $type,
        public string $quantity,
        public string $unitCode,
        public ?int $totalCost,
        public ?string $currencyCode,
        public ?CarbonImmutable $expirationDate,
        public ?DeductionStrategy $strategy,
        public ?array $stockIds,
        public ?array $metadata,
        public ?array $stockMetadata,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, MasterInterface $masterInterface, bool $costsInMinor = false): self
    {
        $type = $data['type'] ?? null;
        $strategy = $data['strategy'] ?? null;

        return new self(
            itemId: $data['item_id'],
            locationId: $data['location_id'],
            toLocationId: $data['to_location_id'] ?? null,
            type: is_string($type) ? MovementType::from($type) : $type,
            quantity: (string) $data['quantity'],
            unitCode: $data['unit_code'],
            totalCost: isset($data['total_cost'], $data['currency_code'])
                ? ($costsInMinor
                    ? (int) $data['total_cost']
                    : (int) $masterInterface->toMinor((string) $data['total_cost'], $data['currency_code']))
                : ($data['total_cost'] ?? null),
            currencyCode: $data['currency_code'] ?? null,
            expirationDate: isset($data['expiration_date']) ? CarbonImmutable::parse($data['expiration_date']) : null,
            strategy: is_string($strategy) ? DeductionStrategy::from($strategy) : $strategy,
            stockIds: $data['stock_ids'] ?? null,
            metadata: $data['metadata'] ?? null,
            stockMetadata: $data['stock_metadata'] ?? null,
        );
    }
}
