<?php

declare(strict_types=1);

namespace Lahatre\Customer\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Lahatre\Customer\Data\CustomerData;
use Lahatre\Customer\Data\CustomerFilterData;
use Lahatre\Customer\Models\Customer;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

class CustomerService
{
    /** @return CursorPaginator<int, Customer> */
    public function paginate(CustomerFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->customersQuery($filters)),
            $filters,
        );
    }

    public function retrieve(Customer $customer): Customer
    {
        return $customer->load(responseRelationsToLoad());
    }

    public function create(CustomerData $data): Customer
    {
        $customer = new Customer;
        $customer->fill([
            'organization_id'       => currentOrganizationId(),
            'type'                  => $data->type,
            'name'                  => $data->name,
            'identification_number' => $data->identificationNumber,
            'is_active'             => $data->isActive instanceof MissingValue
                ? true
                : $data->isActive,
        ]);

        return DB::transaction(function () use ($customer): Customer {
            $customer->save();

            return $customer->load(responseRelationsToLoad());
        });
    }

    public function update(Customer $customer, CustomerData $data): Customer
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($customer, $data, $organizationId): Customer {
            $lockedCustomer = $this->lockCustomer($customer, $organizationId);
            $lockedCustomer->fill(withoutMissing([
                'type'                  => $data->type,
                'name'                  => $data->name,
                'identification_number' => $data->identificationNumber,
                'is_active'             => $data->isActive,
            ]));
            $lockedCustomer->save();

            return $lockedCustomer->fresh()->load(responseRelationsToLoad());
        });
    }

    public function delete(Customer $customer): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($customer, $organizationId): void {
            $lockedCustomer = $this->lockCustomer($customer, $organizationId);
            $lockedCustomer->addresses()->delete();
            $lockedCustomer->contacts()->delete();
            $lockedCustomer->delete();
        });
    }

    private function lockCustomer(Customer $customer, string $organizationId): Customer
    {
        if ($customer->organization_id !== $organizationId) {
            throw (new ModelNotFoundException)->setModel(Customer::class, [$customer->getKey()]);
        }

        return Customer::query()
            ->where('organization_id', $organizationId)
            ->whereKey($customer->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return Builder<Customer> */
    private function customersQuery(CustomerFilterData $filters): Builder
    {
        /** @var Builder<Customer> $query */
        $query = Customer::query()
            ->where('organization_id', currentOrganizationId());

        if ($filters->name) {
            $query->where('name', 'like', "{$filters->name}%");
        }

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        return $query
            ->orderBy($filters->sortBy, $filters->sortOrder)
            ->orderBy('id', $filters->sortOrder);
    }
}
