<?php

declare(strict_types=1);

namespace Lahatre\Customer\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Customer\Exceptions\CustomerFileException;
use Lahatre\Customer\Models\Customer;
use Lahatre\Library\Contracts\LibraryInterface;
use Lahatre\Library\Models\FileAttachment;

final readonly class CustomerFileService
{
    public function __construct(private LibraryInterface $attachments) {}

    /** @param list<string> $fileIds */
    public function setProfilePicture(Customer $customer, array $fileIds): Customer
    {
        DB::transaction(function () use ($customer, $fileIds): void {
            $ownedCustomer = Customer::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (!$ownedCustomer->is_active) {
                throw CustomerFileException::customerInactive($customer->id);
            }

            $this->attachments->replaceAttachments($ownedCustomer, 'profile_picture', $fileIds);
        });

        return $customer->load(responseRelationsToLoad());
    }

    public function find(Customer $customer, string $attachmentId): FileAttachment
    {
        return $this->attachments->find($customer, $attachmentId);
    }
}
