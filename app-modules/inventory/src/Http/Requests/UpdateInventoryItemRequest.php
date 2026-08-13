<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Enums\DeductionStrategy;

class UpdateInventoryItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku'                    => ['string', 'max:255'],
            'stock_tracking_enabled' => ['boolean'],
            'is_expirable'           => ['boolean'],
            'deduction_strategy'     => ['nullable', Rule::enum(DeductionStrategy::class)],
        ];
    }
}
