<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Catalog\Http\Requests\Concerns\ValidatesPriceAmount;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Shared\Rules\BulkExists;

final class CatalogItemPriceUpdateRequest extends FormRequest
{
    use ValidatesPriceAmount;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $price = $this->route('price');
        $precision = $price instanceof CatalogItemPrice
            ? $this->precisionsByLevel(collect([$price->price_level_id]))->get($price->price_level_id)
            : null;

        // an omitted field is kept; an empty list of taxes makes it a price before tax
        return [
            'amount'           => $this->amountRules($precision ?? 8),
            'included_tax_ids' => [
                'array',
                'max:'.config('child-limits.catalog_item_price_taxes.catalog_item_price_id'),
                new BulkExists('tax_taxes', 'id', 'id', 'uuid', true, ['organization_id' => currentOrganizationId()]),
            ],
            'included_tax_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
