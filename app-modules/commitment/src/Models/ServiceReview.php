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
 * @property string|null $batch_id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $decision
 * @property string|null $comment
 * @property string $actor_email
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 */
class ServiceReview extends Model
{
    use SharedTraits;

    protected $table = 'commitment_reviews';

    protected $fillable = [
        'organization_id',
        'commitment_id',
        'batch_id',
        'subject_type',
        'subject_id',
        'decision',
        'comment',
        'actor_email',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'commitment_id'   => 'string',
        'batch_id'        => 'string',
        'subject_type'    => 'string',
        'subject_id'      => 'string',
        'decision'        => 'string',
        'comment'         => 'string',
        'actor_email'     => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];
}
