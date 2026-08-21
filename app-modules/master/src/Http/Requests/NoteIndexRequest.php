<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Master\Enums\NoteKind;
use Lahatre\Master\Enums\NoteVisibility;

class NoteIndexRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'per_page'     => ['integer', 'min:1', 'max:100'],
            'cursor'       => ['nullable', 'string'],
            'notable_type' => ['nullable', 'string', 'max:100'],
            'notable_id'   => ['nullable', 'uuid'],
            'kind'         => ['nullable', Rule::enum(NoteKind::class)],
            'visibility'   => ['nullable', Rule::enum(NoteVisibility::class)],
        ];
    }
}
