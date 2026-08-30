<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Data\ContactCreateData;
use Lahatre\Master\Data\ContactUpdateData;
use Lahatre\Master\Exceptions\ContactException;
use Lahatre\Master\Models\Contact;
use Lahatre\Master\Traits\InteractsWithContacts;

use function Lahatre\Shared\Data\withoutMissing;

final class ContactService
{
    /**
     * @param  array<int, ContactCreateData>  $contacts
     * @return Collection<int, Contact>
     */
    public function addMultiple(Model $contactable, array $contacts): Collection
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($contactable, $contacts, $organizationId): Collection {
            $lockedContactable = $this->lockContactable($contactable, $organizationId);
            $primaryCount = count(array_filter($contacts, fn (ContactCreateData $contact): bool => $contact->isPrimary));

            if ($primaryCount > 1) {
                throw ContactException::multiplePrimary();
            }

            if ($primaryCount === 1) {
                $this->contacts($lockedContactable)->update(['is_primary' => false]);
            }

            $timestamp = now();
            $contactRows = [];
            $contactIds = [];
            foreach ($contacts as $contact) {
                $contactId = (string) Str::uuid7();
                $contactIds[] = $contactId;
                $contactRows[] = [
                    'id'               => $contactId,
                    'organization_id'  => $organizationId,
                    'contactable_type' => $lockedContactable->getMorphClass(),
                    'contactable_id'   => $lockedContactable->getKey(),
                    'type'             => $contact->type,
                    'value'            => $contact->value,
                    'is_primary'       => $contact->isPrimary,
                    'created_at'       => $timestamp,
                    'updated_at'       => $timestamp,
                ];
            }

            DB::table('master_contacts')->insert($contactRows);

            /** @var Collection<int, Contact> $created */
            $created = $this->contacts($lockedContactable)
                ->whereIn('master_contacts.id', $contactIds)
                ->get();

            return $created;
        });
    }

    public function update(Model $contactable, Contact $contact, ContactUpdateData $data): Contact
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($contactable, $contact, $data, $organizationId): Contact {
            $lockedContactable = $this->lockContactable($contactable, $organizationId);
            $ownedContact = $this->contacts($lockedContactable)
                ->whereKey($contact->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $updates = withoutMissing([
                'type'       => $data->type,
                'value'      => $data->value,
                'is_primary' => $data->isPrimary,
            ]);

            if (($updates['is_primary'] ?? false) === true) {
                $this->contacts($lockedContactable)
                    ->where('master_contacts.id', '!=', $ownedContact->getKey())
                    ->update(['is_primary' => false]);
            }

            $ownedContact->fill($updates)->save();

            return $ownedContact->fresh();
        });
    }

    /** @param array<int, string> $contactIds */
    public function removeMultiple(Model $contactable, array $contactIds): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($contactable, $contactIds, $organizationId): void {
            $lockedContactable = $this->lockContactable($contactable, $organizationId);
            $contacts = $this->contacts($lockedContactable)
                ->whereIn('id', $contactIds)
                ->lockForUpdate()
                ->get();

            $invalidContactIds = array_values(array_diff(array_unique($contactIds), $contacts->modelKeys()));
            if ($invalidContactIds !== []) {
                throw ContactException::invalidIds($invalidContactIds);
            }

            $this->contacts($lockedContactable)
                ->whereIn('master_contacts.id', $contactIds)
                ->delete();
        });
    }

    private function lockContactable(Model $contactable, string $organizationId): Model
    {
        $this->assertModelUsesContacts($contactable);

        if ($contactable->getAttribute('organization_id') !== $organizationId) {
            throw (new ModelNotFoundException)->setModel($contactable::class, [$contactable->getKey()]);
        }

        return $contactable::query()
            ->where('organization_id', $organizationId)
            ->whereKey($contactable->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return MorphMany<Contact, Model> */
    private function contacts(Model $contactable): MorphMany
    {
        if (!method_exists($contactable, 'contacts')) {
            throw ContactException::modelMissingInteractsWithContactsTrait($contactable::class);
        }

        return $contactable->contacts();
    }

    private function assertModelUsesContacts(Model $model): void
    {
        if (!in_array(InteractsWithContacts::class, class_uses_recursive($model::class), true)) {
            throw ContactException::modelMissingInteractsWithContactsTrait($model::class);
        }
    }
}
