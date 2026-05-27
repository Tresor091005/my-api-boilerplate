<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Pricing parties must be tenant-scoped Eloquent models exposing a non-null
 * `organization_id` attribute.
 *
 * @phpstan-require-extends Model
 */
interface HasPricingParty
{
    public function getMorphClass();

    public function getKey();

    /**
     * @return array<string, mixed>
     */
    public function toPricingPartySummary(): array;
}
