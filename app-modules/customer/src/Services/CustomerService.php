<?php

declare(strict_types=1);

namespace Lahatre\Customer\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Lahatre\Customer\Contracts\CustomerUsageInterface;
use Lahatre\Customer\Contracts\CustomerInterface;
use Lahatre\Customer\Data\CustomerData;
use Lahatre\Customer\Data\CustomerFilterData;
use Lahatre\Customer\Enums\CustomerType;
use Lahatre\Customer\Exceptions\CustomerException;
use Lahatre\Customer\Models\Customer;
use Lahatre\Library\Contracts\LibraryInterface;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\TransactionLock;

class CustomerService implements CustomerInterface
{
    public function __construct(
        private readonly LibraryInterface $attachmentService,
        private readonly CustomerUsageInterface $customerUsage,
    ) {}

    /** @return CursorPaginator<int, Customer> */
    public function paginate(CustomerFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            $this->customersQuery($filters),
            $filters,
        );
    }

    public function retrieve(Customer $customer): Customer
    {
        return $customer;
    }

    public function create(CustomerData $data): Customer
    {
        $customer = new Customer;
        $customer->fill([
            'organization_id'       => currentOrganizationId(),
            'type'                  => $data->type,
            'name'                  => $data->name,
            'identification_number' => $data->identificationNumber,
            'price_level_id'        => $data->priceLevelId,
            'payment_due_date'      => $data->paymentDueDate,
            'is_active'             => required($data->isActive),
        ]);

        return DB::transaction(function () use ($customer): Customer {
            $customer->save();

            return $customer;
        });
    }

    /**
     * Updates the customer under its row lock.
     *
     * @throws CustomerException If the due date is emptied while credit sales follow it.
     */
    public function update(Customer $customer, CustomerData $data): Customer
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($customer, $data, $organizationId): Customer {
            $lockedCustomer = $this->lockCustomer($customer, $organizationId);

            if ($data->paymentDueDate === null
                && $lockedCustomer->payment_due_date !== null
                && $this->customerUsage->followsDueDate($organizationId, $lockedCustomer->id)) {
                throw CustomerException::dueDateIsFollowed($lockedCustomer->id);
            }

            $lockedCustomer->fill(withoutMissing([
                'type'                  => $data->type,
                'name'                  => $data->name,
                'identification_number' => $data->identificationNumber,
                'is_active'             => $data->isActive,
                'price_level_id'        => $data->priceLevelId,
                'payment_due_date'      => $data->paymentDueDate,
            ]));
            $lockedCustomer->save();

            return $lockedCustomer->fresh();
        });
    }

    public function delete(Customer $customer): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($customer, $organizationId): void {
            $lockedCustomer = $this->lockCustomer($customer, $organizationId);

            if ($lockedCustomer->is_walk_in) {
                throw CustomerException::walkInCannotBeDeleted($lockedCustomer->id);
            }

            if ($this->customerUsage->isInUse($organizationId, $lockedCustomer->id)) {
                throw CustomerException::inUse($lockedCustomer->id);
            }

            $lockedCustomer->addresses()->delete();
            $lockedCustomer->contacts()->delete();
            $this->attachmentService->detachAll($lockedCustomer);
            $lockedCustomer->delete();
        });
    }

    public function walkInCustomer(string $organizationId): Customer
    {
        $customer = $this->findWalkIn($organizationId);

        if ($customer !== null) {
            return $customer;
        }

        TransactionLock::acquire("customer.walk-in:{$organizationId}");

        return $this->findWalkIn($organizationId) ?? $this->createWalkIn($organizationId);
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

    private function findWalkIn(string $organizationId): ?Customer
    {
        return Customer::query()
            ->where('organization_id', $organizationId)
            ->where('is_walk_in', true)
            ->first();
    }

    private function createWalkIn(string $organizationId): Customer
    {
        $customer = new Customer;
        $customer->fill([
            'organization_id' => $organizationId,
            'type'            => CustomerType::Individual,
            'name'            => __('customer::messages.walk_in_customer_name'),
            'is_active'       => true,
        ]);
        $customer->is_walk_in = true;
        $customer->save();

        return $customer;
    }
}
