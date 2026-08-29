<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Master\Data\AddressUpdateData;
use Lahatre\Master\Exceptions\AddressException;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Traits\InteractsWithAddresses;

use function Lahatre\Shared\Data\withoutMissing;

final class AddressService
{
    /**
     * @param  array<int, AddressCreateData>  $addresses
     * @return Collection<string, Address>
     */
    public function addMultiple(Model $addressable, array $addresses): Collection
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($addressable, $addresses, $organizationId): Collection {
            $lockedAddressable = $this->lockAddressable($addressable, $organizationId);
            $primaryCount = count(array_filter($addresses, fn (AddressCreateData $address): bool => $address->isPrimary));

            if ($primaryCount > 1) {
                throw AddressException::multiplePrimary();
            }

            if ($primaryCount === 1) {
                $this->addresses($lockedAddressable)->update(['is_primary' => false]);
            }

            $timestamp = now();
            $addressRows = [];
            $addressIds = [];
            foreach ($addresses as $address) {
                $addressId = (string) Str::uuid7();
                $addressIds[] = $addressId;
                $addressRows[] = [
                    'id'               => $addressId,
                    'organization_id'  => $organizationId,
                    'addressable_type' => $lockedAddressable->getMorphClass(),
                    'addressable_id'   => $lockedAddressable->getKey(),
                    'line'             => $address->line,
                    'city'             => $address->city,
                    'country'          => $address->country,
                    'is_primary'       => $address->isPrimary,
                    'created_at'       => $timestamp,
                    'updated_at'       => $timestamp,
                ];
            }

            DB::table('master_addresses')->insert($addressRows);

            /** @var Collection<string, Address> $createdById */
            $createdById = $this->addresses($lockedAddressable)
                ->whereIn('master_addresses.id', $addressIds)
                ->get()
                ->keyBy('id');

            return $createdById;
        });
    }

    public function update(Model $addressable, Address $address, AddressUpdateData $data): Address
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($addressable, $address, $data, $organizationId): Address {
            $lockedAddressable = $this->lockAddressable($addressable, $organizationId);
            $ownedAddress = $this->addresses($lockedAddressable)
                ->whereKey($address->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $updates = withoutMissing([
                'line'       => $data->line,
                'city'       => $data->city,
                'country'    => $data->country,
                'is_primary' => $data->isPrimary,
            ]);

            if (($updates['is_primary'] ?? false) === true) {
                $this->addresses($lockedAddressable)
                    ->where('master_addresses.id', '!=', $ownedAddress->getKey())
                    ->update(['is_primary' => false]);
            }

            $ownedAddress->fill($updates)->save();

            return $ownedAddress->fresh();
        });
    }

    /** @param array<int, string> $addressIds */
    public function removeMultiple(Model $addressable, array $addressIds): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($addressable, $addressIds, $organizationId): void {
            $lockedAddressable = $this->lockAddressable($addressable, $organizationId);
            $addresses = $this->addresses($lockedAddressable)
                ->whereIn('id', $addressIds)
                ->lockForUpdate()
                ->get();

            $invalidAddressIds = array_values(array_diff(array_unique($addressIds), $addresses->modelKeys()));
            if ($invalidAddressIds !== []) {
                throw AddressException::invalidIds($invalidAddressIds);
            }

            $this->addresses($lockedAddressable)
                ->whereIn('master_addresses.id', $addressIds)
                ->delete();
        });
    }

    private function lockAddressable(Model $addressable, string $organizationId): Model
    {
        $this->assertModelUsesAddresses($addressable);

        if ($addressable->getAttribute('organization_id') !== $organizationId) {
            throw (new ModelNotFoundException)->setModel($addressable::class, [$addressable->getKey()]);
        }

        return $addressable::query()
            ->where('organization_id', $organizationId)
            ->whereKey($addressable->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return MorphMany<Address, Model> */
    private function addresses(Model $addressable): MorphMany
    {
        if (!method_exists($addressable, 'addresses')) {
            throw AddressException::modelMissingInteractsWithAddressesTrait($addressable::class);
        }

        return $addressable->addresses();
    }

    private function assertModelUsesAddresses(Model $model): void
    {
        if (!in_array(InteractsWithAddresses::class, class_uses_recursive($model::class), true)) {
            throw AddressException::modelMissingInteractsWithAddressesTrait($model::class);
        }
    }
}
