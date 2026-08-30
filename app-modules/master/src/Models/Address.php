<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Database\Factories\AddressFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $line
 * @property string $city
 * @property string $country
 * @property bool $is_primary
 * @property string $organization_id
 * @property string $addressable_type
 * @property string $addressable_id
 * @property string $country_code
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Model|\Eloquent $addressable
 *
 * @method static AddressFactory factory($count = null, $state = [])
 * @method static Builder<static>|Address newModelQuery()
 * @method static Builder<static>|Address newQuery()
 * @method static Builder<static>|Address onlyTrashed()
 * @method static Builder<static>|Address query()
 * @method static Builder<static>|Address whereAddressableId($value)
 * @method static Builder<static>|Address whereAddressableType($value)
 * @method static Builder<static>|Address whereCity($value)
 * @method static Builder<static>|Address whereCreatedAt($value)
 * @method static Builder<static>|Address whereDeletedAt($value)
 * @method static Builder<static>|Address whereId($value)
 * @method static Builder<static>|Address whereIsPrimary($value)
 * @method static Builder<static>|Address whereLine($value)
 * @method static Builder<static>|Address whereOrganizationId($value)
 * @method static Builder<static>|Address whereUpdatedAt($value)
 * @method static Builder<static>|Address withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Address withoutTrashed()
 * @method static Builder<static>|Address whereCountry($value)
 *
 * @mixin \Eloquent
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
