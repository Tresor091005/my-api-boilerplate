<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Lahatre\Master\Validation\AddressPayloadRules;

final class StockLocationRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name'      => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:100'],
            'is_active' => ['boolean'],
            ...AddressPayloadRules::singleRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::sanitize($this->input('name'))]);
        }
    }
}
