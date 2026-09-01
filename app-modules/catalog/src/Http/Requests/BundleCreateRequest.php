<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Rules\ValidBundleItems;
use Lahatre\Inventory\Validation\InventoryItemPayloadRules;
use Lahatre\Master\Contracts\MasterInterface;

final class BundleCreateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [];

        if (is_string($this->input('name'))) {
            $prepared['name'] = Str::sanitize($this->input('name'));
        }
        if (is_string($this->input('sku'))) {
            $prepared['sku'] = Str::toUpper($this->input('sku'));
        }

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = currentOrganizationId();
        $allowedTypes = array_map(
            fn (CatalogItemType $type): string => $type->value,
            CatalogItemType::allowedBundleComponentTypes(),
        );

        return [
            'name'              => ['required', 'string', 'max:150'],
            'sku'               => ['nullable', 'string', 'max:100', Rule::unique('catalog_items', 'sku')->where('organization_id', $organizationId)],
            'is_active'         => ['boolean'],
            'items'             => ['required', 'array', 'min:2', 'max:50', new ValidBundleItems($organizationId, app(MasterInterface::class))],
            'items.*.item_type' => ['required', 'string', Rule::in($allowedTypes)],
            'items.*.item_id'   => ['required', 'uuid', 'distinct'],
            'items.*.quantity'  => ['required', 'integer', 'min:1'],
            'items.*.unit_code' => ['required', 'string', 'max:100'],
            ...InventoryItemPayloadRules::rules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            InventoryItemPayloadRules::validate($validator, $this->all(), 'inventory');
        }];
    }
}
