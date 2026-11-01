<?php

declare(strict_types=1);

namespace Lahatre\Tax\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Shared\Traits\SharedTraits;
use Lahatre\Tax\Database\Factories\TaxProfileLineBracketFactory;

/**
 * Replaces only the `value` of one bracket of the line's tax.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $tax_profile_line_id
 * @property string $tax_bracket_id
 * @property string $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TaxProfileLine $line
 * @property-read TaxBracket $bracket
 *
 * @method static TaxProfileLineBracketFactory factory($count = null, $state = [])
 */
class TaxProfileLineBracket extends Model
{
    use SharedTraits;

    protected $table = 'tax_profile_line_brackets';

    protected $fillable = [
        'organization_id',
        'tax_profile_line_id',
        'tax_bracket_id',
        'value',
    ];

    protected $casts = [
        'id'                  => 'string',
        'organization_id'     => 'string',
        'tax_profile_line_id' => 'string',
        'tax_bracket_id'      => 'string',
        'value'               => 'decimal:6',
        'created_at'          => 'immutable_datetime',
        'updated_at'          => 'immutable_datetime',
    ];

    /** @return BelongsTo<TaxProfileLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(TaxProfileLine::class, 'tax_profile_line_id');
    }

    /** @return BelongsTo<TaxBracket, $this> */
    public function bracket(): BelongsTo
    {
        return $this->belongsTo(TaxBracket::class, 'tax_bracket_id');
    }
}
