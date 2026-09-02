<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\StockLocationFactory;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Traits\InteractsWithInventoryLocation;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Traits\InteractsWithAddresses;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $handle
 * @property string $name
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Address|null $address
 * @property-read InventoryLocation|null $inventoryLocation
 *
 * @method static Builder<static>|StockLocation newModelQuery()
 * @method static Builder<static>|StockLocation newQuery()
 * @method static Builder<static>|StockLocation query()
 * @method static Builder<static>|StockLocation whereCreatedAt($value)
 * @method static Builder<static>|StockLocation whereDeletedAt($value)
 * @method static Builder<static>|StockLocation whereHandle($value)
 * @method static Builder<static>|StockLocation whereId($value)
 * @method static Builder<static>|StockLocation whereIsActive($value)
 * @method static Builder<static>|StockLocation whereName($value)
 * @method static Builder<static>|StockLocation whereOrganizationId($value)
 * @method static Builder<static>|StockLocation whereUpdatedAt($value)
 * @method static Builder<static>|StockLocation onlyTrashed()
 * @method static Builder<static>|StockLocation withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StockLocation withoutTrashed()
 * @method static \Lahatre\Catalog\Database\Factories\StockLocationFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class StockLocation extends Model implements HasInventoryLocation
{
    /** @use HasFactory<StockLocationFactory> */
    use HasFactory;

    use InteractsWithAddresses;
    use InteractsWithInventoryLocation;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_stock_locations';

    protected $fillable = [
        'organization_id',
        'handle',
        'name',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'handle'          => 'string',
        'name'            => 'string',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')
            ->where('master_addresses.organization_id', currentOrganizationId())
            ->where('master_addresses.is_primary', true);
    }
}
