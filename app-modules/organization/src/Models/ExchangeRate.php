<?php

declare(strict_types=1);

namespace Lahatre\Organization\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Organization\Database\Factories\ExchangeRateFactory;
use Lahatre\Organization\Enums\ExchangeRateContext;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $source_currency_code
 * @property string $target_currency_code
 * @property ExchangeRateContext $context
 * @property string $rate
 * @property CarbonImmutable $effective_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|ExchangeRate newModelQuery()
 * @method static Builder<static>|ExchangeRate newQuery()
 * @method static Builder<static>|ExchangeRate query()
 * @method static ExchangeRateFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class ExchangeRate extends Model
{
    use SharedTraits;

    protected $table = 'organization_exchange_rates';

    protected $fillable = [
        'organization_id',
        'source_currency_code',
        'target_currency_code',
        'context',
        'rate',
        'effective_at',
    ];

    protected $casts = [
        'id'                   => 'string',
        'organization_id'      => 'string',
        'source_currency_code' => 'string',
        'target_currency_code' => 'string',
        'context'              => ExchangeRateContext::class,
        'rate'                 => 'decimal:12',
        'effective_at'         => 'immutable_datetime',
        'created_at'           => 'immutable_datetime',
        'updated_at'           => 'immutable_datetime',
    ];
}
