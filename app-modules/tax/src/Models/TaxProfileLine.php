<?php

declare(strict_types=1);

namespace Lahatre\Tax\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Shared\Traits\SharedTraits;
use Lahatre\Tax\Database\Factories\TaxProfileLineFactory;
use Lahatre\Tax\Enums\TaxTreatment;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $tax_profile_id
 * @property string $tax_id
 * @property TaxTreatment $treatment
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TaxProfile $profile
 * @property-read Tax $tax
 * @property-read Collection<int, TaxProfileLineBracket> $bracketOverrides
 *
 * @method static TaxProfileLineFactory factory($count = null, $state = [])
 */
class TaxProfileLine extends Model
{
    use SharedTraits;

    protected $table = 'tax_profile_lines';

    protected $fillable = [
        'organization_id',
        'tax_profile_id',
        'tax_id',
        'treatment',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'tax_profile_id'  => 'string',
        'tax_id'          => 'string',
        'treatment'       => TaxTreatment::class,
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    /** @return BelongsTo<TaxProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'tax_profile_id');
    }

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** @return HasMany<TaxProfileLineBracket, $this> */
    public function bracketOverrides(): HasMany
    {
        return $this->hasMany(TaxProfileLineBracket::class);
    }
}
