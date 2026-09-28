<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EvidenceReviewRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items'               => ['required', 'array', 'min:1', 'max:100'],
            'items.*.evidence_id' => ['required', 'uuid', 'distinct'],
            'items.*.decision'    => ['required', Rule::in(['accept', 'request_changes', 'reject', 'revoke'])],
            'items.*.comment'     => ['nullable', 'string', 'max:5000'],
        ];
    }
}
