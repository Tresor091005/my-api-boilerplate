<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Master\Enums\NoteKind;

class NoteUpdateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'body'       => ['string', 'min:1', 'max:10000'],
            'kind'       => [Rule::enum(NoteKind::class)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
