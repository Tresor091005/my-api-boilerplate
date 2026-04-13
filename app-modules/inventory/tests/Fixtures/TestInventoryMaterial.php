<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\ProvidesInventoryItemableSummary;
use Lahatre\Inventory\Traits\InteractsWithInventoryItem;
use Lahatre\Shared\Traits\SharedTraits;

class TestInventoryMaterial extends Model implements HasInventoryItem, ProvidesInventoryItemableSummary
{
    use InteractsWithInventoryItem;
    use SharedTraits;

    protected $table = 'test_materials';

    protected $fillable = [
        'name',
        'sku',
        'unit_group_id',
        'is_active',
    ];

    protected $casts = [
        'id'            => 'string',
        'name'          => 'string',
        'sku'           => 'string',
        'unit_group_id' => 'string',
        'is_active'     => 'boolean',
    ];

    public function getUnitGroupId(): string
    {
        return (string) $this->unit_group_id;
    }

    public function getSku(): string
    {
        return (string) $this->sku;
    }

    /**
     * @return array<string, mixed>
     */
    public function toInventoryItemableSummary(): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'sku'  => $this->sku,
        ];
    }
}
