<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Illuminate\Support\Collection;

final readonly class ProductVariantBatchData
{
    /**
     * @param  Collection<int, ProductVariantData>  $variants
     */
    private function __construct(
        public Collection $variants,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            variants: collect($data['variants'])
                ->map(fn (array $variant): ProductVariantData => ProductVariantData::fromArray($variant)),
        );
    }
}
