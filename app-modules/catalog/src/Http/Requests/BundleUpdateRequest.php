<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Inventory\Validation\InventoryItemPayloadRules;

final class BundleUpdateRequest extends FormRequest
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
        $bundle = $this->route('bundle');

        return [
            'name' => ['string', 'max:150'],
            'sku'  => [
                'string',
                'max:100',
                Rule::unique('catalog_items', 'sku')
                    ->where('organization_id', currentOrganizationId())
                    ->ignore($bundle instanceof Bundle ? $bundle->id : null),
            ],
            'is_active' => ['boolean'],
            'items'     => ['prohibited'],
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
