<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-require-extends Model
 */
interface HasInventoryLocation
{
    /**
     * Return the lightweight representation exposed under location.external.
     *
     * @return array<string, mixed>
     */
    public function toInventoryLocationSummary(): array;
}
