<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Models\InventoryLocation;

/**
 * @phpstan-require-extends Model
 */
interface HasInventoryLocation
{
    /**
     * @return MorphOne<InventoryLocation, Model>
     */
    public function inventoryLocation(): MorphOne;

    public function getMorphClass();

    /**
     * @return string|int|null
     */
    public function getKey();

    public function getOrganizationId(): string;

    /**
     * Return the lightweight representation exposed under location.external.
     *
     * @return array<string, mixed>
     */
    public function toInventoryLocationExternalSummary(): array;
}
