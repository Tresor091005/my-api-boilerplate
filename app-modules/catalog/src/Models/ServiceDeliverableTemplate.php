<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $service_id
 * @property string $name
 * @property int $position
 * @property CarbonImmutable|null $deleted_at
 */
class ServiceDeliverableTemplate extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_service_deliverable_templates';

    protected $fillable = ['organization_id', 'service_id', 'name', 'position'];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'service_id'      => 'string',
        'name'            => 'string',
        'position'        => 'integer',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'id')
            ->where('catalog_services.organization_id', currentOrganizationId());
    }
}
