<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Pricing\Contracts\HasPricingParty;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 */
class TestPricingParty extends Model implements HasPricingParty
{
    use SharedTraits;

    protected $table = 'test_pricing_parties';

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

    /**
     * @return array<string, mixed>
     */
    public function toPricingPartySummary(): array
    {
        return [
            'id'   => $this->id,
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
