<?php

declare(strict_types=1);

namespace Lahatre\Service\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Service\Enums\CommitmentStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $organization_id
 * @property string $id
 * @property string $service_id
 * @property CommitmentStatus $status
 * @property-read Collection<int, ServiceDeliverable> $deliverables
 */
class ServiceCommitment extends Model
{
    use SharedTraits;

    protected $table = 'service_commitments';

    protected $fillable = [
        'organization_id', 'service_id', 'sale_line_id', 'status',
        'confirmed_at', 'completed_at', 'closed_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'service_id'      => 'string',
        'sale_line_id'    => 'string',
        'status'          => CommitmentStatus::class,
        'confirmed_at'    => 'immutable_datetime',
        'completed_at'    => 'immutable_datetime',
        'closed_at'       => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    /** @return HasMany<ServiceDeliverable, $this> */
    public function deliverables(): HasMany
    {
        return $this->hasMany(ServiceDeliverable::class, 'commitment_id')
            ->where('service_deliverables.organization_id', currentOrganizationId())
            ->orderBy('position');
    }
}
