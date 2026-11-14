<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lahatre\Catalog\Database\Factories\CatalogItemPriceTaxFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * One tax the amount of a price already contains.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $catalog_item_price_id
 * @property string $tax_id
 *
 * @method static Builder<static>|CatalogItemPriceTax newModelQuery()
 * @method static Builder<static>|CatalogItemPriceTax newQuery()
 * @method static Builder<static>|CatalogItemPriceTax query()
 * @method static CatalogItemPriceTaxFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class CatalogItemPriceTax extends Pivot
{
    use SharedTraits;

    public $timestamps = false;

    protected $table = 'catalog_item_price_taxes';

    protected $fillable = [
        'organization_id',
        'catalog_item_price_id',
        'tax_id',
    ];

    protected $casts = [
        'id'                    => 'string',
        'organization_id'       => 'string',
        'catalog_item_price_id' => 'string',
        'tax_id'                => 'string',
    ];
}
