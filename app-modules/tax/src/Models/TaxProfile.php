<?php

declare(strict_types=1);

namespace Lahatre\Tax\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Shared\Traits\SharedTraits;
use Lahatre\Tax\Database\Factories\TaxProfileFactory;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, TaxProfileLine> $lines
 *
 * @method static TaxProfileFactory factory($count = null, $state = [])
 */
class TaxProfile extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'tax_profiles';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'code'            => 'string',
        'name'            => 'string',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    /** @return HasMany<TaxProfileLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TaxProfileLine::class);
    }
}
