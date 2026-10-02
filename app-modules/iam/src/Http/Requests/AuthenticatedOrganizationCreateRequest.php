<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Shared\Rules\IanaTimezone;

class AuthenticatedOrganizationCreateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::sanitize($this->input('name'))]);
        }
        if (is_string($this->input('currency_code'))) {
            $this->merge(['currency_code' => Str::toUpper(Str::sanitize($this->input('currency_code')))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:100'],
            'currency_code' => ['required', 'string', 'size:3', 'alpha', Rule::exists('master_currencies', 'code')->whereNull('deleted_at')],
            'timezone'      => ['required', 'string', new IanaTimezone],
        ];
    }
}
