<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Master\Enums\NoteKind;
use Lahatre\Master\Enums\NoteVisibility;

class NoteCreateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'notable_type' => ['required', 'string', 'max:100'],
            'notable_id'   => ['required', 'uuid'],
            'parent_id'    => ['nullable', 'uuid'],
            'body'         => ['required', 'string', 'min:1', 'max:10000'],
            'kind'         => ['required', Rule::enum(NoteKind::class)],
            'visibility'   => ['required', Rule::enum(NoteVisibility::class)],
            'expires_at'   => ['nullable', 'date', 'after:now'],
            'member_ids'   => [
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
