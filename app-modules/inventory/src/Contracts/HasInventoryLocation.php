<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Marks a model that can own an InventoryLocation.
 *
 * @phpstan-require-extends Model
 */
interface HasInventoryLocation {}
