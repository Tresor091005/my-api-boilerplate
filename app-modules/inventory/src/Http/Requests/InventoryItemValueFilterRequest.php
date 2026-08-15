<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryItemValueFilterRequest extends FormRequest
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
            'location_id'     => ['nullable', 'array', 'min:1', 'max:100'],
            'location_id.*'   => ['uuid'],
            'currency_code'   => ['nullable', 'array', 'min:1', 'max:100'],
            'currency_code.*' => ['string', 'size:3'],
        ];
    }
}
