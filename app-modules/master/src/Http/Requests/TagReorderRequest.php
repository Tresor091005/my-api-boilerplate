<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TagReorderRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type'      => ['required', 'string', 'max:255'],
            'tag_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'tag_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
