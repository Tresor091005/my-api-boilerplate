<?php

declare(strict_types=1);

namespace Lahatre\Tax\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Traits\SharedTraits;
use Lahatre\Tax\Database\Factories\TaxFactory;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $code
 * @property string $name
 * @property TaxKind $kind
 * @property TaxCalculationType $calculation_type
 * @property TaxFixedMode|null $fixed_mode
 * @property string|null $tax_unit_id
 * @property TaxBaseType $base_type
 * @property bool $is_progressive
 * @property TaxBracketMode|null $bracket_mode
 * @property TaxRoundingMode $rounding_mode
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, TaxBracket> $brackets
 * @property-read Collection<int, TaxDependency> $dependencies
 * @property-read Unit|null $taxUnit
 *
 * @method static TaxFactory factory($count = null, $state = [])
 */
class Tax extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'tax_taxes';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'kind',
        'calculation_type',
        'fixed_mode',
        'tax_unit_id',
        'base_type',
        'is_progressive',
        'bracket_mode',
        'rounding_mode',
        'is_active',
    ];

    protected $casts = [
        'id'               => 'string',
        'organization_id'  => 'string',
        'code'             => 'string',
        'name'             => 'string',
        'kind'             => TaxKind::class,
        'calculation_type' => TaxCalculationType::class,
        'fixed_mode'       => TaxFixedMode::class,
        'tax_unit_id'      => 'string',
        'base_type'        => TaxBaseType::class,
        'is_progressive'   => 'boolean',
        'bracket_mode'     => TaxBracketMode::class,
        'rounding_mode'    => TaxRoundingMode::class,
        'is_active'        => 'boolean',
        'created_at'       => 'immutable_datetime',
        'updated_at'       => 'immutable_datetime',
        'deleted_at'       => 'immutable_datetime',
    ];

    /** @return HasMany<TaxBracket, $this> */
    public function brackets(): HasMany
    {
        return $this->hasMany(TaxBracket::class)->orderBy('base_min');
    }

    /** @return HasMany<TaxDependency, $this> */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaxDependency::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function taxUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'tax_unit_id')->withTrashed();
    }
}
