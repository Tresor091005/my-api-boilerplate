<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Lahatre\Master\Validation\TagPayloadRules;

class TagCreateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return TagPayloadRules::rules(required: true);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            TagPayloadRules::validate($validator, $this->all(), 'tags');
        });
    }
}
