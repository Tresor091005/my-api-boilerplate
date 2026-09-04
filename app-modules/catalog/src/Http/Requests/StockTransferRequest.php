<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Enums\DeductionStrategy;

final class StockTransferRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'source_location_id' => [
                'required', 'uuid',
                Rule::exists('catalog_stock_locations', 'id')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at'),
            ],
            'destination_location_id' => [
                'required', 'uuid', 'different:source_location_id',
                Rule::exists('catalog_stock_locations', 'id')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at'),
            ],
            'lines'                   => ['required', 'array', 'min:1', 'max:100'],
            'lines.*'                 => ['required', 'array'],
            'lines.*.catalog_item_id' => ['required', 'uuid', 'distinct'],
            'lines.*.quantity'        => ['required', 'integer', 'gt:0'],
            'lines.*.unit_code'       => ['required', 'string', 'max:100'],
            'lines.*.strategy'        => ['nullable', Rule::enum(DeductionStrategy::class)],
            'lines.*.stock_ids'       => ['nullable', 'array', 'max:100', 'distinct'],
            'lines.*.stock_ids.*'     => ['string', 'uuid'],
        ];
    }
}
