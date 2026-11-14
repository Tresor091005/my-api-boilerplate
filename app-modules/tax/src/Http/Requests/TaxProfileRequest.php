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
 * lines and their custom grids. Tax ownership and the tiling of a custom grid
 * are checked by the service.
 */
class TaxProfileRequest extends FormRequest
{
    private const string DECIMAL = 'regex:/^\d{1,18}(\.\d{1,6})?$/';

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
            'code'                              => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'],
            'name'                              => ['required', 'string', 'max:100'],
            'is_active'                         => ['boolean'],
            'lines'                             => ['required', 'array', 'min:1', 'max:'.config('child-limits.tax_profile_lines.tax_profile_id')],
            'lines.*'                           => ['required', 'array'],
            'lines.*.tax_id'                    => ['required', 'uuid', 'distinct'],
            'lines.*.treatment'                 => ['required', Rule::enum(TaxTreatment::class)],
            'lines.*.brackets'                  => ['array', 'max:'.config('child-limits.tax_brackets.tax_profile_line_id')],
            'lines.*.brackets.*'                => ['required', 'array'],
            'lines.*.brackets.*.base_min'       => ['required', 'string', self::DECIMAL],
            'lines.*.brackets.*.base_max'       => ['nullable', 'string', self::DECIMAL],
            'lines.*.brackets.*.value'          => ['required', 'string', self::DECIMAL],
            'lines.*.brackets.*.tax_amount_min' => ['nullable', 'string', self::DECIMAL],
            'lines.*.brackets.*.tax_amount_max' => ['nullable', 'string', self::DECIMAL],
        ];
    }
}
