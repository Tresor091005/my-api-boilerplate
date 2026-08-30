<?php

declare(strict_types=1);

namespace Lahatre\Organization\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Organization\Database\Factories\OrganizationSettingFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property array<int, string> $enable_currencies
 * @property string $timezone
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|OrganizationSetting newModelQuery()
 * @method static Builder<static>|OrganizationSetting newQuery()
 * @method static Builder<static>|OrganizationSetting query()
 * @method static OrganizationSettingFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrganizationSetting whereCreatedAt($value)
 * @method static Builder<static>|OrganizationSetting whereEnableCurrencies($value)
 * @method static Builder<static>|OrganizationSetting whereId($value)
 * @method static Builder<static>|OrganizationSetting whereOrganizationId($value)
 * @method static Builder<static>|OrganizationSetting whereTimezone($value)
 * @method static Builder<static>|OrganizationSetting whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class OrganizationSetting extends Model
{
    use SharedTraits;

    protected $table = 'organization_settings';

    protected $fillable = [
        'organization_id',
        'enable_currencies',
        'timezone',
    ];

    protected $casts = [
        'id'                => 'string',
        'organization_id'   => 'string',
        'enable_currencies' => 'array',
        'timezone'          => 'string',
        'created_at'        => 'immutable_datetime',
        'updated_at'        => 'immutable_datetime',
    ];
}
