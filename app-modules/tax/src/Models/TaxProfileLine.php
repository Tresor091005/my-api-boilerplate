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
 * @property-read Collection<int, TaxBracket> $brackets
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
        return $this->belongsTo(TaxProfile::class, 'tax_profile_id')
            ->where('tax_profiles.organization_id', currentOrganizationId());
    }

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class)
            ->where('tax_taxes.organization_id', currentOrganizationId());
    }

    /**
     * The grid that replaces the tax's own grid under a custom treatment.
     *
     * @return HasMany<TaxBracket, $this>
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(TaxBracket::class)
            ->where('tax_brackets.organization_id', currentOrganizationId())
            ->orderBy('base_min');
    }
}
