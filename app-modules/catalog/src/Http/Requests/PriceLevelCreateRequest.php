<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class PriceLevelCreateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::normalize($this->input('name'))]);
        }
        if (is_string($this->input('currency_code'))) {
            $this->merge(['currency_code' => Str::toUpper($this->input('currency_code'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('master_currencies', 'code')->whereNull('deleted_at')],
            'name'          => [
                'required',
                'string',
                'max:100',
                Rule::unique('catalog_price_levels', 'name')
                    ->where('organization_id', currentOrganizationId())
                    ->where('currency_code', is_string($this->input('currency_code')) ? $this->input('currency_code') : '')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
