<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanDraftRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'price'                   => ['decimal:0,8', 'min:0', 'required_with:currency_code'],
            'currency_code'           => ['required_with:price', 'string', 'size:3', Rule::exists('master_currencies', 'code')->whereNull('deleted_at')],
            'billing_interval_months' => ['integer', 'min:1', 'max:'.config('billing.max_advance_months')],
        ];
    }
}
