<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProposalReviewRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proposal_id' => ['required', 'uuid'],
            'decision'    => ['required', Rule::in(['accept', 'request_changes'])],
            'comment'     => ['required_if:decision,request_changes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
