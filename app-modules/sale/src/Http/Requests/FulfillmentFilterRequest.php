<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Sale\Enums\FulfillmentStatus;

final class FulfillmentFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['created_at', 'number'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'status'     => ['nullable', Rule::enum(FulfillmentStatus::class)],
        ];
    }
}
