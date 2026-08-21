<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lahatre\Master\Database\Factories\LabelableFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $label_id
 * @property string $labelable_type
 * @property string $labelable_id
 * @property-read Label $label
 * @property-read Model $labelable
 *
 * @method static Builder<static>|Labelable newModelQuery()
 * @method static Builder<static>|Labelable newQuery()
 * @method static Builder<static>|Labelable query()
 * @method static Builder<static>|Labelable whereId($value)
 * @method static Builder<static>|Labelable whereLabelId($value)
 * @method static Builder<static>|Labelable whereLabelableId($value)
 * @method static Builder<static>|Labelable whereLabelableType($value)
 * @method static LabelableFactory factory($count = null, $state = [])
 * @method static Builder<static>|Labelable whereOrganizationId($value)
 *
 * @mixin \Eloquent
 */
class Labelable extends Pivot
{
    use SharedTraits;

    public $timestamps = false;

    protected $table = 'master_labelables';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'label_id',
        'labelable_type',
        'labelable_id',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'label_id'        => 'string',
        'labelable_type'  => 'string',
        'labelable_id'    => 'string',
    ];

    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class, 'label_id', 'id')
            ->where('master_labels.organization_id', currentOrganizationId());
    }

    public function labelable(): MorphTo
    {
        return $this->morphTo()
            ->where('organization_id', currentOrganizationId());
    }
}
