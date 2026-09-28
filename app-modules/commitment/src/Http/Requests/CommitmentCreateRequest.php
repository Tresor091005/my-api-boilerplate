<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CommitmentCreateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_id'   => ['required', 'uuid'],
            'customer_id'  => ['required', 'uuid'],
            'client_email' => ['required', 'email', 'max:254'],
            'title'        => ['nullable', 'string', 'max:150'],
            'terms'        => ['nullable', 'string', 'max:10000'],
        ];
    }
}
