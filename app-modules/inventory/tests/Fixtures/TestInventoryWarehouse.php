<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\ProvidesInventoryLocationExternalSummary;
use Lahatre\Inventory\Traits\InteractsWithInventoryLocation;
use Lahatre\Shared\Traits\SharedTraits;

class TestInventoryWarehouse extends Model implements HasInventoryLocation, ProvidesInventoryLocationExternalSummary
{
    use InteractsWithInventoryLocation;
    use SharedTraits;

    protected $table = 'test_warehouses';

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'id'        => 'string',
        'name'      => 'string',
        'code'      => 'string',
        'is_active' => 'boolean',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toInventoryLocationExternalSummary(): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'code' => $this->code,
        ];
    }
}
