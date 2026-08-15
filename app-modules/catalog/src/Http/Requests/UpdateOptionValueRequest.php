<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;

class UpdateOptionValueRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('value'))) {
            $this->merge(['value' => Str::normalize($this->input('value'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $option = $this->route('option');
        $value = $this->route('value');

        return [
            'value' => [
                'string',
                'max:100',
                Rule::unique('catalog_option_values', 'value')
                    ->where(fn ($query) => $query
                        ->where('option_id', $option instanceof Option ? $option->id : null)
                        ->where('organization_id', currentOrganizationId())
                        ->whereNull('deleted_at'))
                    ->ignore($value instanceof OptionValue ? $value : null),
            ],
        ];
    }
}
