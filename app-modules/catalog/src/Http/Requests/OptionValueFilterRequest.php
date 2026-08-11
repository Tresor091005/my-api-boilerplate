<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OptionValueFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sort_by'    => ['string', Rule::in(['value', 'created_at', 'updated_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'value'      => ['nullable', 'string', 'max:255'],
        ];
    }
}
