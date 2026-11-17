<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Sale\Http\Requests\Concerns\ValidatesFulfillmentLines;

/** A draft fulfillment is created and replaced whole with the same lines. */
final class FulfillmentRequest extends FormRequest
{
    use ValidatesFulfillmentLines;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return $this->fulfillmentLinesRules(required: true);
    }
}
