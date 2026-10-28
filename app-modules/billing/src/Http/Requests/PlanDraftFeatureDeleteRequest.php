<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlanDraftFeatureDeleteRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'feature_keys'   => ['required', 'array', 'list', 'min:1', 'max:100'],
            'feature_keys.*' => ['required', 'string', 'distinct'],
        ];
    }
}
