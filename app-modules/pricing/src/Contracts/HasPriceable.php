<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Pricing priceables must be tenant-scoped Eloquent models exposing a non-null
 * `organization_id` attribute.
 *
 * @phpstan-require-extends Model
 */
interface HasPriceable
{
    public function getMorphClass();

    public function getKey();

    public function getPricingUnitGroupId(): string;

    public function getDefaultPricingUnitCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function toPriceableSummary(): array;
}
