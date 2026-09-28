<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Shared\Rules\Rfc3339Utc;

final class EvidenceCreateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'outcome'           => ['required', Rule::in(['performed', 'missed', 'cancelled', 'rescheduled'])],
            'narrative'         => ['nullable', 'string', 'max:10000'],
            'reason'            => ['required_if:outcome,missed,cancelled,rescheduled', 'nullable', 'string', 'max:10000'],
            'scheduled_at'      => ['required_if:outcome,rescheduled', 'nullable', new Rfc3339Utc],
            'performed_at'      => ['nullable', new Rfc3339Utc],
            'quantity'          => ['required_with:display_unit_code', 'nullable', 'numeric', 'gt:0', 'regex:/\A(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,4})?\z/D'],
            'display_unit_code' => ['required_with:quantity', 'nullable', 'string', 'max:50'],
            'file_ids'          => ['array', 'max:20'],
            'file_ids.*'        => ['required', 'uuid', 'distinct'],
        ];
    }
}
