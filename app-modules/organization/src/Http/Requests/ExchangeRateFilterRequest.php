<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Organization\Enums\ExchangeRateContext;

class ExchangeRateFilterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['source_currency_code', 'target_currency_code'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::toUpper($this->input($field))]);
            }
        }

        if (is_string($this->input('context'))) {
            $this->merge(['context' => Str::normalize($this->input('context'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'             => ['integer', 'min:1', 'max:100'],
            'cursor'               => ['nullable', 'string'],
            'source_currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'target_currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'context'              => ['nullable', Rule::enum(ExchangeRateContext::class)],
            'effective_from'       => ['nullable', 'date'],
            'effective_to'         => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
