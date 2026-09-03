<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Enums\BundleStockOperationType;

final readonly class BundleStockOperationData
{
    /**
     * @param  Collection<int, BundleStockOperationComponentData>  $components
     * @param  list<string>  $sourceStockIds
     */
    private function __construct(
        public BundleStockOperationType $type,
        public int $quantity,
        public string $locationId,
        public ?CarbonImmutable $expirationDate,
        public array $sourceStockIds,
        public Collection $components,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $expirationDate = $data['expiration_date'] ?? null;

        return new self(
            type: BundleStockOperationType::from($data['type']),
            quantity: (int) $data['quantity'],
            locationId: $data['location_id'],
            expirationDate: is_string($expirationDate)
                ? CarbonImmutable::createFromFormat('!Y-m-d', $expirationDate)
                : null,
            sourceStockIds: array_values($data['stock_ids'] ?? []),
            components: collect($data['components'] ?? [])
                ->map(BundleStockOperationComponentData::fromArray(...))
                ->values(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'type'            => $this->type->value,
            'quantity'        => $this->quantity,
            'location_id'     => $this->locationId,
            'expiration_date' => $this->expirationDate?->toDateString(),
            'stock_ids'       => $this->sourceStockIds,
            'components'      => $this->components->map(
                fn (BundleStockOperationComponentData $component): array => [
                    'bundle_item_id'  => $component->bundleItemId,
                    'stock_ids'       => $component->stockIds,
                    'expiration_date' => $component->expirationDate?->toDateString(),
                ],
            )->all(),
        ];
    }
}
