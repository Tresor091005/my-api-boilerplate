<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Shared\Rules\Rfc3339Utc;

class ExchangeRateUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate'         => ['required', 'string', 'regex:/^[0-9]+(?:\.[0-9]{1,12})?$/', 'not_regex:/^0+(?:\.0{1,12})?$/'],
            'effective_at' => ['required', new Rfc3339Utc, 'after:now'],
        ];
    }
}
