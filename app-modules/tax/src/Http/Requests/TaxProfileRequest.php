<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Tax\Enums\TaxTreatment;

/**
 * Create and update share one complete definition: an update replaces the
 * lines and their bracket overrides. Tax and bracket ownership is checked by
 * the service.
 */
class TaxProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::toUpper(Str::sanitize($this->input('code')))]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::sanitize($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code'                                       => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'],
            'name'                                       => ['required', 'string', 'max:100'],
            'is_active'                                  => ['boolean'],
            'lines'                                      => ['required', 'array', 'min:1', 'max:50'],
            'lines.*'                                    => ['required', 'array'],
            'lines.*.tax_id'                             => ['required', 'uuid', 'distinct'],
            'lines.*.treatment'                          => ['required', Rule::enum(TaxTreatment::class)],
            'lines.*.bracket_overrides'                  => ['array', 'max:50'],
            'lines.*.bracket_overrides.*'                => ['required', 'array'],
            'lines.*.bracket_overrides.*.tax_bracket_id' => ['required', 'uuid'],
            'lines.*.bracket_overrides.*.value'          => ['required', 'string', 'regex:/^\d{1,18}(\.\d{1,6})?$/'],
        ];
    }
}
