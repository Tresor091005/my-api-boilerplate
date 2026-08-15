<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Models\Option;

class OptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [];

        if (is_string($this->input('name'))) {
            $prepared['name'] = Str::normalize($this->input('name'));
        }

        if (is_array($this->input('values'))) {
            $values = Arr::where($this->input('values'), fn (mixed $value): bool => is_string($value));
            $prepared['values'] = array_values(array_unique(array_map(
                fn (string $value): string => Str::normalize($value),
                $values,
            )));
        }

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $option = $this->route('option');
        $isUpdate = $option instanceof Option;

        return [
            'name' => [
                ...($isUpdate ? [] : ['required']),
                'string',
                'max:100',
                Rule::unique('catalog_options', 'name')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at')
                    ->ignore($isUpdate ? $option : null),
            ],
            'values'   => ['nullable', 'array', 'max:100'],
            'values.*' => ['string', 'max:100'],
        ];
    }
}
