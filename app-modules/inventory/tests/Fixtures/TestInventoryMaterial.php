<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Traits\InteractsWithInventoryItem;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $sku
 * @property string $unit_group_id
 * @property bool $is_active
 */
class TestInventoryMaterial extends Model implements HasInventoryItem
{
    use InteractsWithInventoryItem;
    use SharedTraits;

    protected $table = 'test_materials';

    protected $fillable = [
        'organization_id',
        'name',
        'sku',
        'unit_group_id',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'name'            => 'string',
        'sku'             => 'string',
        'unit_group_id'   => 'string',
        'is_active'       => 'boolean',
    ];

    public function getUnitGroupId(): string
    {
        return (string) $this->unit_group_id;
    }

    public function getSku(): string
    {
        return (string) $this->sku;
    }
}
