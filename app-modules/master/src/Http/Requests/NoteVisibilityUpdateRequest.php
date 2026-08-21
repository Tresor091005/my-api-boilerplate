<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Master\Enums\NoteVisibility;

class NoteVisibilityUpdateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'visibility' => ['required', Rule::enum(NoteVisibility::class)],
            'member_ids' => [
                'array',
                'min:1',
                'max:100',
                'required_if:visibility,mentioned',
                'prohibited_unless:visibility,mentioned',
            ],
            'member_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
