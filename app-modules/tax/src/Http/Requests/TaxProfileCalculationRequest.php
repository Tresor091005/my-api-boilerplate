<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxProfileCalculationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'base_amount' => ['required', 'string', 'regex:/^\d{1,18}$/'],
            'quantity'    => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,10})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'unit_code'   => [
                'nullable',
                'string',
                'max:100',
                Rule::exists('master_units', 'code')
                    ->whereNull('deleted_at')
                    ->where(fn (Builder $query): Builder => $query
                        ->whereNull('organization_id')
                        ->orWhere('organization_id', currentOrganizationId())),
            ],
        ];
    }
}
