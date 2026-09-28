<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Shared\Rules\Rfc3339Utc;

final class DeliverableRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title'             => [...($this->isMethod('POST') ? ['required'] : []), 'string', 'max:150'],
            'description'       => ['nullable', 'string', 'max:10000'],
            'scheduled_at'      => ['nullable', new Rfc3339Utc],
            'quantity'          => ['required_with:display_unit_code', 'nullable', 'numeric', 'gt:0', 'regex:/\A(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,4})?\z/D'],
            'display_unit_code' => ['required_with:quantity', 'nullable', 'string', 'max:50'],
        ];
    }
}
