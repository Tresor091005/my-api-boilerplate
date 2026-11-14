<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Shared\Rules\BulkExists;

final class CatalogItemPriceDeleteRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $catalogItem = $this->route('item');

        return [
            'ids' => [
                'required',
                'array',
                'min:1',
                'max:'.config('child-limits.catalog_item_prices.catalog_item_id'),
                new BulkExists('catalog_item_prices', 'id', 'id', 'uuid', true, [
                    'organization_id' => currentOrganizationId(),
                    'catalog_item_id' => $catalogItem instanceof CatalogItem ? $catalogItem->id : null,
                ]),
            ],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
