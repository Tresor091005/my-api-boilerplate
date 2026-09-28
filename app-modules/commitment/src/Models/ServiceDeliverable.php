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
 * @property string $title
 * @property string|null $description
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $performed_at
 * @property string|null $quantity
 * @property string|null $display_unit_code
 * @property string $execution_state
 * @property string $validation_state
 * @property string|null $accepted_evidence_id
 * @property string|null $current_evidence_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ServiceDeliverable extends Model
{
    use SharedTraits;

    protected $table = 'commitment_deliverables';

    protected $fillable = [
        'organization_id',
        'commitment_id',
        'title',
        'description',
        'scheduled_at',
        'performed_at',
        'quantity',
        'display_unit_code',
        'execution_state',
        'validation_state',
        'accepted_evidence_id',
        'current_evidence_id',
    ];

    protected $casts = [
        'id'                   => 'string',
        'organization_id'      => 'string',
        'commitment_id'        => 'string',
        'title'                => 'string',
        'description'          => 'string',
        'scheduled_at'         => 'immutable_datetime',
        'performed_at'         => 'immutable_datetime',
        'quantity'             => 'decimal:4',
        'display_unit_code'    => 'string',
        'execution_state'      => 'string',
        'validation_state'     => 'string',
        'accepted_evidence_id' => 'string',
        'current_evidence_id'  => 'string',
        'created_at'           => 'immutable_datetime',
        'updated_at'           => 'immutable_datetime',
    ];
}
