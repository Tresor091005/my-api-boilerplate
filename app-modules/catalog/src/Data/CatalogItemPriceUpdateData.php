<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

/**
 * What changes on an existing price: its amount, a decimal string in the
 * currency of its price level, and the taxes that amount already contains.
 * A missing field is kept; an empty list of taxes makes it a price before tax.
 */
final readonly class CatalogItemPriceUpdateData
{
    /** @param MissingValue|list<string> $includedTaxIds */
    private function __construct(
        public MissingValue|string $amount,
        public MissingValue|array $includedTaxIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $amount = $read->get('amount');
        $includedTaxIds = $read->get('included_tax_ids');

        return new self(
            amount: $amount instanceof MissingValue ? $amount : (string) $amount,
            includedTaxIds: $includedTaxIds instanceof MissingValue
                ? $includedTaxIds
                : array_values(array_unique(array_map(strval(...), $includedTaxIds))),
        );
    }
}
