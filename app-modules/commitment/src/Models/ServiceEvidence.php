<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Traits\InteractsWithFile;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $commitment_id
 * @property string $deliverable_id
 * @property int $version
 * @property string $title
 * @property string|null $description
 * @property string $outcome
 * @property string|null $narrative
 * @property string|null $reason
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $performed_at
 * @property string|null $quantity
 * @property string|null $display_unit_code
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ServiceEvidence extends Model implements HasFileSlots
{
    use InteractsWithFile;
    use SharedTraits;

    public function fileSlots(): array
    {
        return ['evidence' => ['max_files' => 20, 'mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain']]];
    }

    protected $table = 'commitment_evidence';

    protected $fillable = [
        'organization_id',
        'commitment_id',
        'deliverable_id',
        'version',
        'title',
        'description',
        'outcome',
        'narrative',
        'reason',
        'scheduled_at',
        'performed_at',
        'quantity',
        'display_unit_code',
        'submitted_at',
    ];

    protected $casts = [
        'id'                => 'string',
        'organization_id'   => 'string',
        'commitment_id'     => 'string',
        'deliverable_id'    => 'string',
        'version'           => 'integer',
        'title'             => 'string',
        'description'       => 'string',
        'outcome'           => 'string',
        'narrative'         => 'string',
        'reason'            => 'string',
        'scheduled_at'      => 'immutable_datetime',
        'performed_at'      => 'immutable_datetime',
        'quantity'          => 'decimal:4',
        'display_unit_code' => 'string',
        'submitted_at'      => 'immutable_datetime',
        'created_at'        => 'immutable_datetime',
        'updated_at'        => 'immutable_datetime',
    ];
}
