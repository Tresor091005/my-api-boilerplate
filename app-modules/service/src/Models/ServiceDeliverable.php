<?php

declare(strict_types=1);

namespace Lahatre\Service\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Service\Enums\DeliverableStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $organization_id
 * @property string $id
 * @property string $commitment_id
 * @property string $name
 * @property DeliverableStatus $status
 * @property-read Collection<int, Evidence> $evidences
 */
class ServiceDeliverable extends Model
{
    use SharedTraits;

    protected $table = 'service_deliverables';

    protected $fillable = [
        'organization_id', 'commitment_id', 'name', 'position', 'status',
        'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'commitment_id'   => 'string',
        'name'            => 'string',
        'position'        => 'integer',
        'status'          => DeliverableStatus::class,
        'completed_at'    => 'immutable_datetime',
        'cancelled_at'    => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    /** @return BelongsTo<ServiceCommitment, $this> */
    public function commitment(): BelongsTo
    {
        return $this->belongsTo(ServiceCommitment::class, 'commitment_id')
            ->where('service_commitments.organization_id', currentOrganizationId());
    }

    /** @return HasMany<Evidence, $this> */
    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class, 'deliverable_id')
            ->where('service_evidences.organization_id', currentOrganizationId());
    }
}
