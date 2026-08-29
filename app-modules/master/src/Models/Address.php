<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $line
 * @property string $city
 * @property string $country
 * @property bool $is_primary
 */
class Address extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_addresses';

    protected $fillable = [
        'organization_id',
        'addressable_type',
        'addressable_id',
        'line',
        'city',
        'country',
        'is_primary',
    ];

    protected $casts = [
        'id'               => 'string',
        'organization_id'  => 'string',
        'addressable_type' => 'string',
        'addressable_id'   => 'string',
        'line'             => 'string',
        'city'             => 'string',
        'country'          => 'string',
        'is_primary'       => 'boolean',
        'created_at'       => 'immutable_datetime',
        'updated_at'       => 'immutable_datetime',
        'deleted_at'       => 'immutable_datetime',
    ];

    public function addressable(): MorphTo
    {
        return $this->morphTo()
            ->where('organization_id', currentOrganizationId());
    }
}
