<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', 'in:name,created_at,updated_at'],
            'sort_order' => ['string', 'in:asc,desc'],
            'name'       => ['nullable', 'string', 'max:100'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
