<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Shared\Rules\YmdDate;

/**
 * `status` is one status or a list of them.
 */
final class SaleFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $status = $this->input('status');

        return [
            'per_page'    => ['integer', 'min:1', 'max:100'],
            'cursor'      => ['nullable', 'string'],
            'sort_by'     => ['string', Rule::in(['created_at', 'number'])],
            'sort_order'  => ['string', Rule::in(['asc', 'desc'])],
            'status'      => is_array($status) ? ['array', 'max:9'] : ['nullable', Rule::enum(SaleStatus::class)],
            'status.*'    => [Rule::enum(SaleStatus::class)],
            'customer_id' => ['nullable', 'uuid'],
            'number'      => ['nullable', 'string', 'max:50'],
            // days of creation, in the organization's timezone, both included
            'from_date' => ['nullable', new YmdDate],
            'to_date'   => ['nullable', new YmdDate, 'after_or_equal:from_date'],
        ];
    }
}
