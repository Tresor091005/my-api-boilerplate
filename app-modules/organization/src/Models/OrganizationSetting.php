<?php

declare(strict_types=1);

namespace Lahatre\Organization\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property array<int, string> $enable_currencies
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|OrganizationSetting newModelQuery()
 * @method static Builder<static>|OrganizationSetting newQuery()
 * @method static Builder<static>|OrganizationSetting query()
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
    ];

    protected $casts = [
        'id'                => 'string',
        'organization_id'   => 'string',
        'enable_currencies' => 'array',
        'created_at'        => 'immutable_datetime',
        'updated_at'        => 'immutable_datetime',
    ];
}
