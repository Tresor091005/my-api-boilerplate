<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryStockExpiringFilterRequest extends FormRequest
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
            'per_page'    => ['integer', 'min:1', 'max:100'],
            'cursor'      => ['nullable', 'string'],
            'days'        => ['integer', 'min:1', 'max:365'],
            'location_id' => ['nullable', 'string'],
            'item_id'     => ['nullable', 'string'],
        ];
    }
}
