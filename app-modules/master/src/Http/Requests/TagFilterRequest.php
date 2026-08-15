<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['name', 'type', 'order_col', 'created_at', 'updated_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'name'       => ['nullable', 'string', 'max:50'],
            'type'       => ['nullable', 'string', 'max:50'],
        ];
    }
}
