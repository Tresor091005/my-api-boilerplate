<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Models\PriceLevel;

/**
 * A level is renamed only: its currency is fixed at creation.
 */
final class PriceLevelUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::normalize($this->input('name'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $priceLevel = $this->route('price_level');
        $isLevel = $priceLevel instanceof PriceLevel;

        return [
            'currency_code' => ['prohibited'],
            'name'          => [
                'required',
                'string',
                'max:100',
                Rule::unique('catalog_price_levels', 'name')
                    ->where('organization_id', currentOrganizationId())
                    ->where('currency_code', $isLevel ? $priceLevel->currency_code : '')
                    ->whereNull('deleted_at')
                    ->ignore($isLevel ? $priceLevel : null),
            ],
        ];
    }
}
