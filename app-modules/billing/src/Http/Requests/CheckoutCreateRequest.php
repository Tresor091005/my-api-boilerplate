<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Billing\Enums\CheckoutAction;

class CheckoutCreateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action'  => ['required', Rule::enum(CheckoutAction::class)],
            'plan_id' => ['required_unless:action,'.CheckoutAction::Renew->value, 'uuid', Rule::exists('billing_plans', 'id')->whereNull('deleted_at')],
            'periods' => ['prohibited_if:action,'.CheckoutAction::ChangeNow->value, 'integer', 'min:1', 'max:'.config('billing.max_advance_months')],
        ];
    }
}
