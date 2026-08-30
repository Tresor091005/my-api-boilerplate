<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Database\Factories\ContactFactory;
use Lahatre\Master\Enums\ContactType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property ContactType $type
 * @property string $value
 * @property bool $is_primary
 * @property string $organization_id
 * @property string $contactable_type
 * @property string $contactable_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Model|\Eloquent $contactable
 *
 * @method static ContactFactory factory($count = null, $state = [])
 * @method static Builder<static>|Contact newModelQuery()
 * @method static Builder<static>|Contact newQuery()
 * @method static Builder<static>|Contact onlyTrashed()
 * @method static Builder<static>|Contact query()
 * @method static Builder<static>|Contact whereContactableId($value)
 * @method static Builder<static>|Contact whereContactableType($value)
 * @method static Builder<static>|Contact whereCreatedAt($value)
 * @method static Builder<static>|Contact whereDeletedAt($value)
 * @method static Builder<static>|Contact whereId($value)
 * @method static Builder<static>|Contact whereIsPrimary($value)
 * @method static Builder<static>|Contact whereOrganizationId($value)
 * @method static Builder<static>|Contact whereType($value)
 * @method static Builder<static>|Contact whereUpdatedAt($value)
 * @method static Builder<static>|Contact whereValue($value)
 * @method static Builder<static>|Contact withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Contact withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Contact extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_contacts';

    protected $fillable = [
        'organization_id',
        'contactable_type',
        'contactable_id',
        'type',
        'value',
        'is_primary',
    ];

    protected $casts = [
        'id'               => 'string',
        'organization_id'  => 'string',
        'contactable_type' => 'string',
        'contactable_id'   => 'string',
        'type'             => ContactType::class,
        'value'            => 'string',
        'is_primary'       => 'boolean',
        'created_at'       => 'immutable_datetime',
        'updated_at'       => 'immutable_datetime',
        'deleted_at'       => 'immutable_datetime',
    ];

    public function contactable(): MorphTo
    {
        return $this->morphTo()
            ->where('organization_id', currentOrganizationId());
    }
}
