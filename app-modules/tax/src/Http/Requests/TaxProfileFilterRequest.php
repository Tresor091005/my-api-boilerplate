<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxProfileFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['code', 'name', 'created_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
