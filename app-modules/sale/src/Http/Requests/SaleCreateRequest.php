<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Sale\Http\Requests\Concerns\ValidatesSaleDraft;

final class SaleCreateRequest extends FormRequest
{
    use ValidatesSaleDraft;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return $this->draftRules();
    }
}
