<?php

declare(strict_types=1);

namespace Lahatre\Master\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Master\Data\AddressUpdateData;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Services\AddressService;

/**
 * @phpstan-require-extends Model
 *
 * @mixin Model
 */
trait InteractsWithAddresses
{
    /** @return MorphMany<Address, $this> */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')
            ->where('master_addresses.organization_id', currentOrganizationId())
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }

    /** @param array<int, AddressCreateData> $addresses */
    public function addAddresses(array $addresses): Collection
    {
        return app(AddressService::class)->addMultiple($this, $addresses);
    }

    public function updateAddress(Address $address, AddressUpdateData $data): Address
    {
        return app(AddressService::class)->update($this, $address, $data);
    }

    /** @param array<int, string> $addressIds */
    public function removeAddresses(array $addressIds): void
    {
        app(AddressService::class)->removeMultiple($this, $addressIds);
    }
}
