<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Illuminate\Support\Collection;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class ProductData
{
    /**
     * @param  MissingValue|array<int, string>|null  $categories
     * @param  MissingValue|Collection<int, ProductVariantData>  $variants
     */
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|string|null $description,
        public MissingValue|array|null $categories,
        public MissingValue|Collection $variants,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields  Fields of this product payload only.
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $variants = $read->get('variants');

        return new self(
            name: $read->get('name'),
            description: $read->get('description', default: null),
            categories: $read->get('categories', default: null),
            variants: $variants instanceof MissingValue
                ? $variants
                : collect($variants)->map(
                    fn (array $variant): ProductVariantData => ProductVariantData::fromArray($variant),
                ),
        );
    }
}
