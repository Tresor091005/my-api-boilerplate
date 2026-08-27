<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryStockFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'             => ['integer', 'min:1', 'max:100'],
            'cursor'               => ['nullable', 'string'],
            'item_id'              => ['nullable', 'array', 'max:100'],
            'item_id.*'            => ['uuid'],
            'location_id'          => ['nullable', 'array', 'max:100'],
            'location_id.*'        => ['uuid'],
            'expiring_before'      => ['nullable', 'date'],
            'expiring_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
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
