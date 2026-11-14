<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Lahatre\Catalog\Http\Requests\Concerns\ValidatesPriceAmount;
use Lahatre\Shared\Rules\BulkExists;
use Lahatre\Shared\Support\Decimal;

final class CatalogItemPriceCreateRequest extends FormRequest
{
    use ValidatesPriceAmount;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'prices' => [
                'required',
                'array',
                'min:1',
                'max:'.config('child-limits.catalog_item_prices.catalog_item_id'),
                new BulkExists('catalog_price_levels', 'id', 'price_level_id', 'uuid', true, [
                    'organization_id' => currentOrganizationId(),
                ]),
            ],
            'prices.*.price_level_id' => ['required', 'uuid'],
            'prices.*.unit_code'      => ['required', 'string', 'max:100'],
            'prices.*.amount'         => $this->amountRules(),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $prices = collect($this->input('prices'));
                $precisions = $this->precisionsByLevel($prices->pluck('price_level_id'));

                foreach ($prices as $index => $price) {
                    $precision = $precisions->get($price['price_level_id']);

                    if ($precision !== null && Decimal::places((string) $price['amount']) > $precision) {
                        $validator->errors()->add(
                            "prices.{$index}.amount",
                            __('catalog::validation.price_amount_precision', ['precision' => $precision]),
                        );
                    }
                }
            },
        ];
    }
}
