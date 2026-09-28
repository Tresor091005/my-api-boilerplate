<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CommitmentUpdateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'terms' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
