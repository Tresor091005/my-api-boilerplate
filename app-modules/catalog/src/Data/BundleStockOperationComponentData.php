<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Carbon\CarbonImmutable;

final readonly class BundleStockOperationComponentData
{
    /**
     * @param  list<string>  $stockIds
     */
    private function __construct(
        public string $bundleItemId,
        public array $stockIds,
        public ?CarbonImmutable $expirationDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $expirationDate = $data['expiration_date'] ?? null;

        return new self(
            bundleItemId: $data['bundle_item_id'],
            stockIds: array_values($data['stock_ids'] ?? []),
            expirationDate: is_string($expirationDate)
                ? CarbonImmutable::createFromFormat('!Y-m-d', $expirationDate)
                : null,
        );
    }
}
