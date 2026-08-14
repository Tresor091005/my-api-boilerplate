<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lahatre\Catalog\Models\Product;
use Lahatre\Shared\Rules\BulkExists;
use Lahatre\Shared\Rules\BulkUnique;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [];

        if (is_string($this->input('name'))) {
            $prepared['name'] = Str::sanitize($this->input('name'));
        }

        if (is_array($this->input('variants'))) {
            $prepared['variants'] = array_map(
                fn (mixed $variant): mixed => is_array($variant) ? $this->normalizeVariant($variant) : $variant,
                $this->input('variants'),
            );
        }

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('product') instanceof Product;
        $organizationId = currentOrganizationId();

        return [
            'name' => [
                ...($isUpdate ? [] : ['required']),
                'string',
                'max:255',
            ],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
            'categories'  => [
                'nullable',
                'array',
                new BulkExists('catalog_categories', handleSoftDelete: true, extraConditions: ['organization_id' => $organizationId]),
            ],
            'variants' => [
                $isUpdate ? 'prohibited' : 'required',
                'array',
                'min:1',
                new BulkExists('master_unit_groups', 'id', 'unit_group_id', 'uuid', true, [
                    fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId),
                ]),
                new BulkUnique('catalog_product_variants', 'sku', 'sku', false, [
                    'organization_id' => $organizationId,
                ]),
            ],
            'variants.*.sku'             => ['nullable', 'string', 'max:255'],
            'variants.*.unit_group_id'   => ['required', 'uuid'],
            'variants.*.is_active'       => ['boolean'],
            'variants.*.options'         => ['required', 'array', 'min:1'],
            'variants.*.options.*.name'  => ['required', 'string', 'max:255'],
            'variants.*.options.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $variants = $this->input('variants');
            if (!is_array($variants)) {
                return;
            }

            foreach ($variants as $index => $variant) {
                if (!is_array($variant) || !is_array($variant['options'] ?? null)) {
                    continue;
                }

                $names = collect($variant['options'])->pluck('name');
                if ($names->unique()->count() !== $names->count()) {
                    $validator->errors()->add("variants.{$index}.options", __('catalog::validation.duplicate_option_names'));
                }
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>
     */
    private function normalizeVariant(array $variant): array
    {
        if (is_string($variant['sku'] ?? null)) {
            $variant['sku'] = Str::toUpper($variant['sku']);
        }

        if (is_array($variant['options'] ?? null)) {
            $variant['options'] = array_map(function (mixed $option): mixed {
                if (!is_array($option)) {
                    return $option;
                }

                return [
                    ...$option,
                    'name'  => is_string($option['name'] ?? null) ? Str::normalize($option['name']) : ($option['name'] ?? null),
                    'value' => is_string($option['value'] ?? null) ? Str::normalize($option['value']) : ($option['value'] ?? null),
                ];
            }, $variant['options']);
        }

        return $variant;
    }
}
