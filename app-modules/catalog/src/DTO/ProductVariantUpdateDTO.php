<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;

class ProductVariantUpdateDTO extends LahatreDTO
{
    public ?string $sku = null;

    public ?string $unit_group_id = null;

    public ?bool $should_manage_stock = null;

    public ?bool $is_active = null;

    /** @var array<int, array{name: string, value: string}>|null */
    public ?array $options = null;

    protected function casts(): array
    {
        return [
            'should_manage_stock' => 'bool',
            'is_active'           => 'bool',
        ];
    }

    protected function defaults(): array
    {
        return [
            'sku'                 => null,
            'unit_group_id'       => null,
            'should_manage_stock' => null,
            'is_active'           => null,
            'options'             => null,
        ];
    }

    protected function beforeValidation(array $data): array
    {
        if (isset($data['sku'])) {
            $data['sku'] = Str::toUpper($data['sku']);
        }

        if (isset($data['options']) && is_array($data['options'])) {
            $data['options'] = array_map(fn (array $option): array => [
                'name'  => isset($option['name']) ? Str::normalize($option['name']) : null,
                'value' => isset($option['value']) ? Str::normalize($option['value']) : null,
            ], $data['options']);
        }

        return $data;
    }

    protected function rules(): array
    {
        return [
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('catalog_product_variants', 'sku')
                    ->where('organization_id', getPermissionsTeamId())
                    ->ignore($this->modelId),
            ],
            'unit_group_id'       => ['nullable', 'uuid', Rule::exists('master_unit_groups', 'id')],
            'should_manage_stock' => ['nullable', 'boolean'],
            'is_active'           => ['nullable', 'boolean'],
            'options'             => ['nullable', 'array'],
            'options.*.name'      => ['required_with:options', 'string', 'max:255'],
            'options.*.value'     => ['required_with:options', 'string', 'max:255'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (!is_array($this->dtoData['options'] ?? null)) {
                return;
            }

            $names = collect($this->dtoData['options'])->pluck('name');

            if ($names->unique()->count() !== $names->count()) {
                $validator->errors()->add('options', __('catalog::validation.duplicate_option_names'));
            }
        });
    }
}
