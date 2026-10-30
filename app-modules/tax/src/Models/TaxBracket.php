<?php

declare(strict_types=1);

namespace Lahatre\Tax\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Shared\Traits\SharedTraits;
use Lahatre\Tax\Database\Factories\TaxBracketFactory;

/**
 * Monetary columns are major units of the organization functional currency;
 * `value` is a percentage or a fixed amount depending on the parent tax.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $tax_id
 * @property string $base_min
 * @property string|null $base_max
 * @property string $value
 * @property string|null $tax_amount_min
 * @property string|null $tax_amount_max
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tax $tax
 *
 * @method static TaxBracketFactory factory($count = null, $state = [])
 */
class TaxBracket extends Model
{
    use SharedTraits;

    protected $table = 'tax_brackets';

    protected $fillable = [
        'organization_id',
        'tax_id',
        'base_min',
        'base_max',
        'value',
        'tax_amount_min',
        'tax_amount_max',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'tax_id'          => 'string',
        'base_min'        => 'decimal:6',
        'base_max'        => 'decimal:6',
        'value'           => 'decimal:6',
        'tax_amount_min'  => 'decimal:6',
        'tax_amount_max'  => 'decimal:6',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
