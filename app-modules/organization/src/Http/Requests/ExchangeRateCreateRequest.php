<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Organization\Enums\ExchangeRateContext;
use Lahatre\Shared\Rules\Rfc3339Utc;

class ExchangeRateCreateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_currency_code' => is_string($this->input('source_currency_code'))
                ? Str::toUpper($this->input('source_currency_code'))
                : $this->input('source_currency_code'),
            'target_currency_code' => is_string($this->input('target_currency_code'))
                ? Str::toUpper($this->input('target_currency_code'))
                : $this->input('target_currency_code'),
            'context' => is_string($this->input('context'))
                ? Str::normalize($this->input('context'))
                : ($this->input('context') ?? 'default'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_currency_code' => [
                'required',
                'string',
                'size:3',
                'alpha',
                Rule::exists('master_currencies', 'code')->whereNull('deleted_at'),
            ],
            'target_currency_code' => [
                'required',
                'string',
                'size:3',
                'alpha',
                Rule::exists('master_currencies', 'code')->whereNull('deleted_at'),
            ],
            'context'      => ['required', Rule::enum(ExchangeRateContext::class)],
            'rate'         => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,12})?$/', 'not_regex:/^0+(?:\.0{1,12})?$/'],
            'effective_at' => ['required', new Rfc3339Utc],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    is_string($this->input('source_currency_code'))
                    && $this->input('source_currency_code') === $this->input('target_currency_code')
                ) {
                    $validator->errors()->add(
                        'target_currency_code',
                        __('organization::validation.same_currency_pair'),
                    );
                }
            },
        ];
    }
}
