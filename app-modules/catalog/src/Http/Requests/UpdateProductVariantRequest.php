<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Validation\TagPayloadRules;

class UpdateProductVariantRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [];

        if (is_string($this->input('sku'))) {
            $prepared['sku'] = Str::toUpper($this->input('sku'));
        }

        if (is_array($this->input('options'))) {
            $prepared['options'] = array_map(function (mixed $option): mixed {
                if (!is_array($option)) {
                    return $option;
                }

                return [
                    ...$option,
                    'name'  => is_string($option['name'] ?? null) ? Str::normalize($option['name']) : ($option['name'] ?? null),
                    'value' => is_string($option['value'] ?? null) ? Str::normalize($option['value']) : ($option['value'] ?? null),
                ];
            }, $this->input('options'));
        }

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $variant = $this->route('variant');

        return [
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('catalog_product_variants', 'sku')
                    ->where('organization_id', currentOrganizationId())
                    ->ignore($variant instanceof ProductVariant ? $variant : null),
            ],
            'unit_group_id'   => ['prohibited'],
            'is_active'       => ['boolean'],
            'options'         => ['array', 'max:100'],
            'options.*.name'  => ['required', 'string', 'max:100'],
            'options.*.value' => ['required', 'string', 'max:100'],
            ...TagPayloadRules::rules('tags', allowEmpty: true),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            TagPayloadRules::validate($validator, $this->all(), 'tags');

            if (!is_array($this->input('options'))) {
                return;
            }

            $names = collect($this->input('options'))->pluck('name');
            if ($names->unique()->count() !== $names->count()) {
                $validator->errors()->add('options', __('catalog::validation.duplicate_option_names'));
            }
        }];
    }
}
