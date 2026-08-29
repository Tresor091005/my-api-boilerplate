<?php

declare(strict_types=1);

namespace Lahatre\Master\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Lahatre\Master\Data\ContactCreateData;
use Lahatre\Master\Data\ContactUpdateData;
use Lahatre\Master\Models\Contact;
use Lahatre\Master\Services\ContactService;

/**
 * @phpstan-require-extends Model
 *
 * @mixin Model
 */
trait InteractsWithContacts
{
    /** @return MorphMany<Contact, $this> */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable')
            ->where('master_contacts.organization_id', currentOrganizationId())
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }

    /** @param array<int, ContactCreateData> $contacts */
    public function addContacts(array $contacts): Collection
    {
        return app(ContactService::class)->addMultiple($this, $contacts);
    }

    public function updateContact(Contact $contact, ContactUpdateData $data): Contact
    {
        return app(ContactService::class)->update($this, $contact, $data);
    }

    /** @param array<int, string> $contactIds */
    public function removeContacts(array $contactIds): void
    {
        app(ContactService::class)->removeMultiple($this, $contactIds);
    }
}
