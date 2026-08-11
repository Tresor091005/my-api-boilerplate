<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryLocationFilterRequest extends FormRequest
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
            'sort_by'       => ['string', Rule::in(['id', 'external_type', 'external_id', 'created_at', 'updated_at'])],
            'sort_order'    => ['string', Rule::in(['asc', 'desc'])],
            'ids'           => ['nullable', 'array', 'min:1'],
            'ids.*'         => ['string'],
            'external_type' => ['required_with:external_id', 'string'],
            'external_id'   => ['required_with:external_type', 'array', 'min:1'],
            'external_id.*' => ['string'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }
}
