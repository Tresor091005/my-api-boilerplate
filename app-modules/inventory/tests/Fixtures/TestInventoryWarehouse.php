<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Traits\InteractsWithInventoryLocation;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 */
class TestInventoryWarehouse extends Model implements HasInventoryLocation
{
    use InteractsWithInventoryLocation;
    use SharedTraits;

    protected $table = 'test_warehouses';

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'name'            => 'string',
        'code'            => 'string',
        'is_active'       => 'boolean',
    ];
}
