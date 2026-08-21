<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LabelReorderRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'group'       => ['required', 'string', 'max:50', 'regex:/^[A-Za-z][A-Za-z0-9_-]{1,49}$/'],
            'label_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'label_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
