<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-require-extends Model
 */
interface HasInventoryItem
{
    public function getUnitGroupId(): string;

    public function getSku(): string;

    /**
     * Return the lightweight representation exposed under item.itemable.
     *
     * @return array<string, mixed>
     */
    public function toInventoryItemSummary(): array;
}
