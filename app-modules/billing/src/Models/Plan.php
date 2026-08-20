<?php

declare(strict_types=1);

namespace Lahatre\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @mixin \Eloquent
 */
class Plan extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'billing_plans';

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'id'         => 'string',
        'code'       => 'string',
        'name'       => 'string',
        'is_active'  => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];

    /** @return HasMany<PlanVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class);
    }
}
