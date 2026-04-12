<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;

class ProductVariantDataDTO extends LahatreDTO
{
    public ?string $sku = null;

    public string $unit_group_id;

    public bool $should_manage_stock;

    public bool $is_active;

    /** @var array<int, array{name: string, value: string}> */
    public array $options;

    protected function casts(): array
    {
        return [
            'should_manage_stock' => 'boolean',
            'is_active'           => 'boolean',
        ];
    }

    protected function defaults(): array
    {
        return [
            'should_manage_stock' => false,
            'is_active'           => false,
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
                    ->where('organization_id', getPermissionsTeamId()),
            ],
            'unit_group_id' => [
                'required',
                'uuid',
                Rule::exists('master_unit_groups', 'id')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', getPermissionsTeamId())),
            ],
            'should_manage_stock' => ['boolean'],
            'is_active'           => ['boolean'],
            'options'             => ['required', 'array', 'min:1'],
            'options.*.name'      => ['required', 'string', 'max:255'],
            'options.*.value'     => ['required', 'string', 'max:255'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (empty($this->dtoData['options'])) {
                return;
            }

            $names = collect($this->dtoData['options'])->pluck('name');

            if ($names->unique()->count() !== $names->count()) {
                $validator->errors()->add('options', __('catalog::validation.duplicate_option_names'));
            }
        });
    }
}
