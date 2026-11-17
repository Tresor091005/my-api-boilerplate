<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Sale\Http\Requests\Concerns\ValidatesFulfillmentLines;

/** `lines` says where each line leaves from: required in practice for counter and bill-and-hold sales, ignored otherwise. */
final class SaleConfirmRequest extends FormRequest
{
    use ValidatesFulfillmentLines;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'keep_approved_prices' => ['boolean'],
            ...$this->fulfillmentLinesRules(required: false),
        ];
    }
}
