<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Shared\Rules\YmdDate;

class InventoryStockFilterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'             => ['integer', 'min:1', 'max:100'],
            'cursor'               => ['nullable', 'string'],
            'item_id'              => ['nullable', 'array', 'max:100'],
            'item_id.*'            => ['uuid'],
            'location_id'          => ['nullable', 'array', 'max:100'],
            'location_id.*'        => ['uuid'],
            'expiring_before'      => ['nullable', new YmdDate],
            'expiring_within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'has_remaining'        => ['nullable', 'boolean'],
            // the order an outgoing movement takes the lots of one item in
            'sort_by'  => ['string', Rule::in(['id', 'deduction'])],
            'strategy' => ['nullable', 'prohibited_unless:sort_by,deduction', Rule::enum(DeductionStrategy::class)],
        ];
    }

    /** Sorting as deducted needs exactly one item. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('sort_by') === 'deduction' && count((array) $this->input('item_id', [])) !== 1) {
                $validator->errors()->add('item_id', __('inventory::validation.deduction_order_needs_one_item'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['item_id', 'location_id'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => [$this->input($key)]]);
            }
        }
    }
}
