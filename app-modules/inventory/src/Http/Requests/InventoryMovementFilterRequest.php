<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Enums\MovementType;

class InventoryMovementFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page'       => ['integer', 'min:1', 'max:100'],
            'cursor'         => ['nullable', 'string'],
            'item_id'        => ['nullable', 'array', 'min:1', 'max:100'],
            'item_id.*'      => ['uuid'],
            'location_id'    => ['nullable', 'array', 'min:1', 'max:100'],
            'location_id.*'  => ['uuid'],
            'from'           => ['nullable', 'date'],
            'to'             => ['nullable', 'date', 'after_or_equal:from'],
            'movement_type'  => ['nullable', Rule::enum(MovementType::class)],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id'   => ['nullable', 'uuid'],
        ];
    }
}
