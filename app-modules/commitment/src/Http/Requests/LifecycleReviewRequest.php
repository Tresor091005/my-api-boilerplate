<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LifecycleReviewRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'decision'   => ['required', Rule::in(['accept', 'reject'])],
        ];
    }
}
