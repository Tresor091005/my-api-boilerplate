<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlanDraftFeatureCreateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'features'             => ['required', 'array', 'list', 'min:1', 'max:100'],
            'features.*.key'       => ['required', 'string', 'distinct'],
            'features.*.allowance' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
