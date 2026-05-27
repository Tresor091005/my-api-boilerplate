<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Pricing\Contracts\HasPriceable;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $sku
 * @property string $unit_group_id
 * @property bool $is_active
 */
class TestPricingPriceable extends Model implements HasPriceable
{
    use SharedTraits;

    protected $table = 'test_pricing_priceables';

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

    public function getPricingUnitGroupId(): string
    {
        return (string) $this->unit_group_id;
    }

    public function getDefaultPricingUnitCode(): string
    {
        return 'test-unit';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPriceableSummary(): array
    {
        return [
            'id'   => $this->id,
            'sku'  => $this->sku,
            'name' => $this->name,
        ];
    }
}
