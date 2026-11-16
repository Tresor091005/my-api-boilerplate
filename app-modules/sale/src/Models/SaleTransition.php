<?php

declare(strict_types=1);

namespace Lahatre\Sale\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Sale\Database\Factories\SaleTransitionFactory;
use Lahatre\Sale\Enums\SaleEvent;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * One gesture on a sale: the event, the status before and after, the member
 * who acted (null once removed, or outside a request) and the reason given.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $sale_id
 * @property SaleEvent $event
 * @property SaleStatus|null $from_status
 * @property SaleStatus $to_status
 * @property string|null $member_id
 * @property string|null $reason
 * @property CarbonImmutable|null $created_at
 *
 * @method static Builder<static>|SaleTransition newModelQuery()
 * @method static Builder<static>|SaleTransition newQuery()
 * @method static Builder<static>|SaleTransition query()
 * @method static SaleTransitionFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SaleTransition extends Model
{
    use SharedTraits;

    public const UPDATED_AT = null;

    protected $table = 'sale_transitions';

    protected $fillable = [
        'organization_id',
        'sale_id',
        'event',
        'from_status',
        'to_status',
        'member_id',
        'reason',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'sale_id'         => 'string',
        'event'           => SaleEvent::class,
        'from_status'     => SaleStatus::class,
        'to_status'       => SaleStatus::class,
        'member_id'       => 'string',
        'reason'          => 'string',
        'created_at'      => 'immutable_datetime',
    ];
}
