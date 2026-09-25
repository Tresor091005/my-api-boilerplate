<?php

declare(strict_types=1);

namespace Lahatre\Customer\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Customer\Database\Factories\CustomerFactory;
use Lahatre\Customer\Enums\CustomerType;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Library\Traits\InteractsWithFile;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Models\Contact;
use Lahatre\Master\Traits\InteractsWithAddresses;
use Lahatre\Master\Traits\InteractsWithContacts;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property CustomerType $type
 * @property string $name
 * @property string|null $identification_number
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Address> $addresses
 * @property-read int|null $addresses_count
 * @property-read Collection<int, Contact> $contacts
 * @property-read int|null $contacts_count
 *
 * @method static CustomerFactory factory($count = null, $state = [])
 * @method static Builder<static>|Customer newModelQuery()
 * @method static Builder<static>|Customer newQuery()
 * @method static Builder<static>|Customer onlyTrashed()
 * @method static Builder<static>|Customer query()
 * @method static Builder<static>|Customer whereCreatedAt($value)
 * @method static Builder<static>|Customer whereDeletedAt($value)
 * @method static Builder<static>|Customer whereId($value)
 * @method static Builder<static>|Customer whereIdentificationNumber($value)
 * @method static Builder<static>|Customer whereIsActive($value)
 * @method static Builder<static>|Customer whereName($value)
 * @method static Builder<static>|Customer whereOrganizationId($value)
 * @method static Builder<static>|Customer whereType($value)
 * @method static Builder<static>|Customer whereUpdatedAt($value)
 * @method static Builder<static>|Customer withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Customer withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Customer extends Model implements HasFileSlots
{
    use InteractsWithAddresses;
    use InteractsWithContacts;
    use InteractsWithFile;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'customer_customers';

    protected $fillable = [
        'organization_id',
        'type',
        'name',
        'identification_number',
        'is_active',
    ];

    protected $casts = [
        'id'                    => 'string',
        'organization_id'       => 'string',
        'type'                  => CustomerType::class,
        'name'                  => 'string',
        'identification_number' => 'string',
        'is_active'             => 'boolean',
        'created_at'            => 'immutable_datetime',
        'updated_at'            => 'immutable_datetime',
        'deleted_at'            => 'immutable_datetime',
    ];

    /** @return array<string, array{max_files: int, mime_types: list<string>}> */
    public function fileSlots(): array
    {
        return [
            'profile_picture' => [
                'max_files'  => 1,
                'mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            ],
        ];
    }

    /** @return MorphMany<FileAttachment, $this> */
    public function profilePictureFileAttachments(): MorphMany
    {
        return $this->fileAttachmentsForSlot('profile_picture');
    }
}
