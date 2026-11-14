<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Catalog\Http\Requests\Concerns\ValidatesPriceAmount;
use Lahatre\Catalog\Models\CatalogItemPrice;

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

        return [
            'amount' => $this->amountRules($precision ?? 8),
        ];
    }
}
