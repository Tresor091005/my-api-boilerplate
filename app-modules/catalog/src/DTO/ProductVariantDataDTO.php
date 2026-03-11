<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lahatre\Shared\DTO\LahatreDTO;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;

class ProductVariantDataDTO extends LahatreDTO
{
    public ?string $sku = null;

    public ?string $unit_group_id = null;

    public bool $manage_stock;

    public bool $is_active;

    /** @var array<int, array{name: string, value: string}>|null */
    public ?array $options = null;

    protected function casts(): array
    {
        return [
            'manage_stock' => new BooleanCast(),
            'is_active'    => new BooleanCast(),
        ];
    }

    protected function defaults(): array
    {
        return [
            'manage_stock' => false,
            'is_active'    => false,
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
            'sku'             => ['nullable', 'string', 'max:255'],
            'unit_group_id'   => ['nullable', 'uuid'],
            'manage_stock'    => ['boolean'],
            'is_active'       => ['boolean'],
            'options'         => ['nullable', 'array'],
            'options.*.name'  => ['required_with:options', 'string', 'max:255'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
        ];
    }

    protected function after(Validator $validator): void
    {
        if (empty($this->dtoData['options'])) {
            return;
        }

        $names = collect($this->dtoData['options'])->pluck('name');

        if ($names->unique()->count() !== $names->count()) {
            $validator->errors()->add('options', __('catalog::validation.duplicate_option_names'));
        }
    }
}
