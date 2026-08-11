<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryStockSummaryFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'      => ['integer', 'min:1', 'max:100'],
            'cursor'        => ['nullable', 'string'],
            'item_id'       => ['nullable', 'array'],
            'item_id.*'     => ['string'],
            'location_id'   => ['nullable', 'array'],
            'location_id.*' => ['string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['item_id', 'location_id'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => [$this->input($key)]]);
            }
        }
    }
}
