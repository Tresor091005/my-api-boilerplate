<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Rules\ValidBundleItems;
use Lahatre\Master\Contracts\MasterInterface;

final class BundleItemCreateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bundle = $this->route('bundle');
        $allowedTypes = array_map(
            fn (CatalogItemType $type): string => $type->value,
            CatalogItemType::allowedBundleComponentTypes(),
        );

        return [
            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
                new ValidBundleItems(
                    currentOrganizationId(),
                    app(MasterInterface::class),
                    $bundle instanceof Bundle ? $bundle->id : null,
                ),
            ],
            'items.*.item_type' => ['required', 'string', Rule::in($allowedTypes)],
            'items.*.item_id'   => ['required', 'uuid', 'distinct'],
            'items.*.quantity'  => ['required', 'integer', 'min:1'],
            'items.*.unit_code' => ['required', 'string', 'max:100'],
        ];
    }
}
