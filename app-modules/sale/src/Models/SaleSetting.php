<?php

declare(strict_types=1);

namespace Lahatre\Sale\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Sale\Database\Factories\SaleSettingFactory;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * The sale settings of an organization, one row at most: the mode a sale is
 * written in when it names none.
 *
 * @property string $id
 * @property string $organization_id
 * @property SaleMode $default_mode
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|SaleSetting newModelQuery()
 * @method static Builder<static>|SaleSetting newQuery()
 * @method static Builder<static>|SaleSetting query()
 * @method static SaleSettingFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SaleSetting extends Model
{
    use SharedTraits;

    protected $table = 'sale_settings';

    protected $fillable = [
        'organization_id',
        'default_mode',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'default_mode'    => SaleMode::class,
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];
}
