<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Enums\StockTransferStatus;

final class StockTransferFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['status', 'created_at', 'completed_at', 'cancelled_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'status'     => ['nullable', Rule::enum(StockTransferStatus::class)],
        ];
    }
}
