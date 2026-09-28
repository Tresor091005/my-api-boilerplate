<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $commitment_id
 * @property string|null $deliverable_id
 * @property string $event_type
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string|null $actor_email
 * @property string|null $from_state
 * @property string|null $to_state
 * @property array<string, mixed>|null $snapshot
 * @property CarbonImmutable|null $created_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ServiceAuditEvent extends Model
{
    use SharedTraits;

    public $timestamps = false;

    protected $table = 'commitment_audit_events';

    protected $fillable = [
        'organization_id',
        'commitment_id',
        'deliverable_id',
        'event_type',
        'actor_type',
        'actor_id',
        'actor_email',
        'from_state',
        'to_state',
        'snapshot',
        'created_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'commitment_id'   => 'string',
        'deliverable_id'  => 'string',
        'event_type'      => 'string',
        'actor_type'      => 'string',
        'actor_id'        => 'string',
        'actor_email'     => 'string',
        'from_state'      => 'string',
        'to_state'        => 'string',
        'snapshot'        => 'array',
        'created_at'      => 'immutable_datetime',
    ];
}
