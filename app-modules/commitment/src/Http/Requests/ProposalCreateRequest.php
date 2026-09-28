<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProposalCreateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:150'],
            'terms' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
