<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Enums\BundleStockOperationType;
use Lahatre\Shared\Rules\YmdDate;

final class BundleStockOperationCreateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::enum(BundleStockOperationType::class)],
            'quantity'    => ['required', 'integer', 'min:1'],
            'location_id' => [
                'required',
                'uuid',
                Rule::exists('catalog_stock_locations', 'id')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at'),
            ],
            'expiration_date' => [
                'prohibited_if:type,'.BundleStockOperationType::Detach->value,
                'nullable',
                new YmdDate,
            ],
            'stock_ids' => [
                'prohibited_if:type,'.BundleStockOperationType::Attach->value,
                'nullable',
                'array',
                'max:100',
                'distinct',
            ],
            'stock_ids.*'                 => ['string', 'uuid'],
            'components'                  => ['required', 'array', 'min:1', 'max:50'],
            'components.*.bundle_item_id' => ['required', 'uuid', 'distinct'],
            'components.*.stock_ids'      => [
                'prohibited_if:type,'.BundleStockOperationType::Detach->value,
                'nullable',
                'array',
                'max:100',
                'distinct',
            ],
            'components.*.stock_ids.*'     => ['string', 'uuid'],
            'components.*.expiration_date' => [
                'prohibited_if:type,'.BundleStockOperationType::Attach->value,
                'nullable',
                new YmdDate,
            ],
        ];
    }
}
