<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Inventory\Services\InventoryService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(InventoryService::class);
});

/*
|--------------------------------------------------------------------------
| Bulk Operations & CRUD
|--------------------------------------------------------------------------
*/

todo('createManyItems throws InvalidArgumentException if models are of mixed types');
todo('createManyLocations skips already existing external_id/type pairs without failing');
todo('updateItem validates the deduction_strategy enum');
todo('deleteItem and deleteLocation perform a soft delete and preserve stock history');

/*
|--------------------------------------------------------------------------
| Atomicity & Concurrency
|--------------------------------------------------------------------------
*/

todo('ensures all stock records are locked for update during a transaction');
todo('rolls back all changes if a business logic error occurs in the middle of a multi-movement transaction');
