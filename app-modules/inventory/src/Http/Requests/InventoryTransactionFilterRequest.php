<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryTransactionFilterRequest extends FormRequest
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
            'per_page'         => ['integer', 'min:1', 'max:100'],
            'cursor'           => ['nullable', 'string'],
            'sort_by'          => ['string', Rule::in(['id', 'reference_type', 'reference_id', 'transaction_type', 'created_at', 'updated_at'])],
            'sort_order'       => ['string', Rule::in(['asc', 'desc'])],
            'ids'              => ['nullable', 'array', 'min:1'],
            'ids.*'            => ['string'],
            'reference_type'   => ['required_with:reference_id', 'string'],
            'reference_id'     => ['required_with:reference_type', 'array', 'min:1'],
            'reference_id.*'   => ['string'],
            'transaction_type' => ['nullable', 'string'],
        ];
    }
}
