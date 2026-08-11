<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['code', 'name', 'group', 'created_at', 'updated_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'code'       => ['nullable', 'string', 'max:255'],
            'name'       => ['nullable', 'string', 'max:255'],
            'group'      => ['nullable', 'string', 'max:255'],
            'is_builtin' => ['nullable', 'boolean'],
        ];
    }
}
