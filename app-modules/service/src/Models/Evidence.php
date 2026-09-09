<?php

declare(strict_types=1);

namespace Lahatre\Service\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Service\Enums\EvidenceStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $organization_id
 * @property string $deliverable_id
 * @property string $id
 * @property EvidenceStatus $status
 * @property string $token
 * @property CarbonImmutable|null $expires_at
 * @property array<string, mixed> $snapshot
 */
class Evidence extends Model
{
    use SharedTraits;

    protected $table = 'service_evidences';

    protected $fillable = [
        'organization_id', 'deliverable_id', 'status', 'token',
        'submitted_at', 'expires_at', 'accepted_at', 'rejected_at',
        'verifier_identifier', 'snapshot',
    ];

    protected $casts = [
        'id'                  => 'string',
        'organization_id'     => 'string',
        'deliverable_id'      => 'string',
        'status'              => EvidenceStatus::class,
        'token'               => 'string',
        'submitted_at'        => 'immutable_datetime',
        'expires_at'          => 'immutable_datetime',
        'accepted_at'         => 'immutable_datetime',
        'rejected_at'         => 'immutable_datetime',
        'verifier_identifier' => 'string',
        'snapshot'            => 'array',
        'created_at'          => 'immutable_datetime',
        'updated_at'          => 'immutable_datetime',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliverable::class, 'deliverable_id')
            ->where('service_deliverables.organization_id', currentOrganizationId());
    }
}
