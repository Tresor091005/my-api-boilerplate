<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property string $customer_id
 * @property string $client_email
 * @property string $public_reference
 * @property string $state
 * @property string|null $accepted_proposal_id
 * @property string|null $pending_lifecycle_request_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ServiceCommitment extends Model
{
    use SharedTraits;

    protected $table = 'commitment_service_commitments';

    protected $fillable = [
        'organization_id',
        'service_id',
        'customer_id',
        'client_email',
        'public_reference',
        'state',
        'accepted_proposal_id',
        'pending_lifecycle_request_id',
    ];

    protected $casts = [
        'id'                           => 'string',
        'organization_id'              => 'string',
        'service_id'                   => 'string',
        'customer_id'                  => 'string',
        'client_email'                 => 'string',
        'public_reference'             => 'string',
        'state'                        => 'string',
        'accepted_proposal_id'         => 'string',
        'pending_lifecycle_request_id' => 'string',
        'created_at'                   => 'immutable_datetime',
        'updated_at'                   => 'immutable_datetime',
    ];
}
