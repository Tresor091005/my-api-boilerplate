<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemFilterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'               => ['integer', 'min:1', 'max:100'],
            'cursor'                 => ['nullable', 'string'],
            'sort_by'                => ['string', Rule::in(['id', 'sku', 'created_at', 'updated_at'])],
            'sort_order'             => ['string', Rule::in(['asc', 'desc'])],
            'sku'                    => ['nullable', 'string', 'max:100'],
            'stock_tracking_enabled' => ['nullable', 'boolean'],
            'base_unit_code'         => ['nullable', 'string', 'max:50'],
        ];
    }
}
