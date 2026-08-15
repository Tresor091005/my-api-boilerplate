<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'    => ['integer', 'min:1', 'max:100'],
            'cursor'      => ['nullable', 'string'],
            'sort_by'     => ['string', Rule::in(['handle', 'name', 'created_at', 'updated_at'])],
            'sort_order'  => ['string', Rule::in(['asc', 'desc'])],
            'handle'      => ['nullable', 'string', 'max:100'],
            'name'        => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
