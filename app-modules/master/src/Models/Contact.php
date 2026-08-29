<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Enums\ContactType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property ContactType $type
 * @property string $value
 * @property bool $is_primary
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
