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
 * @property int $version
 * @property string $title
 * @property string|null $terms
 * @property string $state
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ServiceCommitmentProposal extends Model
{
    use SharedTraits;

    protected $table = 'commitment_proposals';

    protected $fillable = [
        'organization_id',
        'commitment_id',
        'version',
        'title',
        'terms',
        'state',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'commitment_id'   => 'string',
        'version'         => 'integer',
        'title'           => 'string',
        'terms'           => 'string',
        'state'           => 'string',
        'submitted_at'    => 'immutable_datetime',
        'reviewed_at'     => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];
}
