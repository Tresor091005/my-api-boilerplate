<?php

declare(strict_types=1);

namespace Lahatre\Organization\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Organization\Database\Factories\OrganizationFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $name
 * @property string $functional_currency_code
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read OrganizationSetting|null $settings
 *
 * @method static Builder<static>|Organization newModelQuery()
 * @method static Builder<static>|Organization newQuery()
 * @method static Builder<static>|Organization onlyTrashed()
 * @method static Builder<static>|Organization query()
 * @method static Builder<static>|Organization whereCreatedAt($value)
 * @method static Builder<static>|Organization whereDeletedAt($value)
 * @method static Builder<static>|Organization whereId($value)
 * @method static Builder<static>|Organization whereName($value)
 * @method static Builder<static>|Organization whereFunctionalCurrencyCode($value)
 * @method static Builder<static>|Organization whereUpdatedAt($value)
 * @method static Builder<static>|Organization withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Organization withoutTrashed()
 * @method static OrganizationFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Organization extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'organization_organizations';

    protected $fillable = [
        'name',
        'functional_currency_code',
    ];

    protected $casts = [
        'id'                       => 'string',
        'name'                     => 'string',
        'functional_currency_code' => 'string',
        'created_at'               => 'immutable_datetime',
        'updated_at'               => 'immutable_datetime',
        'deleted_at'               => 'immutable_datetime',
    ];

    /** @return HasOne<OrganizationSetting, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }
}
