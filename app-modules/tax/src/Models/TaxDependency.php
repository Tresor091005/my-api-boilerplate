<?php

declare(strict_types=1);

namespace Lahatre\Tax\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Shared\Traits\SharedTraits;
use Lahatre\Tax\Database\Factories\TaxDependencyFactory;
use Lahatre\Tax\Enums\TaxDependencyOperator;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $tax_id
 * @property string $depends_on_tax_id
 * @property TaxDependencyOperator $operator
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tax $tax
 * @property-read Tax $dependsOnTax
 *
 * @method static TaxDependencyFactory factory($count = null, $state = [])
 */
class TaxDependency extends Model
{
    use SharedTraits;

    protected $table = 'tax_dependencies';

    protected $fillable = [
        'organization_id',
        'tax_id',
        'depends_on_tax_id',
        'operator',
    ];

    protected $casts = [
        'id'                => 'string',
        'organization_id'   => 'string',
        'tax_id'            => 'string',
        'depends_on_tax_id' => 'string',
        'operator'          => TaxDependencyOperator::class,
        'created_at'        => 'immutable_datetime',
        'updated_at'        => 'immutable_datetime',
    ];

    /** @return BelongsTo<Tax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** @return BelongsTo<Tax, $this> */
    public function dependsOnTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'depends_on_tax_id');
    }
}
