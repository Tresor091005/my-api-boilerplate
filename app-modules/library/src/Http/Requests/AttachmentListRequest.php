<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachmentListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attachment_ids'   => ['present', 'array', 'list', 'max:20'],
            'attachment_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
