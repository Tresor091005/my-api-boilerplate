<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lines to simulate: amounts in minor units of the functional currency, each
 * with the taxes it already contains, if any. Profile ownership, the per-unit
 * checks and what an amount may contain belong to the service.
 */
class TaxSimulationRequest extends FormRequest
{
    /** Same cap as the lists attached to a record. */
    private const int MAX_LINES = 250;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'include_inactive'       => ['boolean'],
            'lines'                  => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*'                => ['required', 'array'],
            'lines.*.tax_profile_id' => ['required', 'uuid'],
            'lines.*.amount'         => ['required', 'string', 'regex:/^\d{1,18}$/'],
            // a tax outside the profile of the line is left out, so no lookup is needed
            'lines.*.included_tax_ids'   => ['array', 'max:'.config('child-limits.tax_profile_lines.tax_profile_id')],
            'lines.*.included_tax_ids.*' => ['uuid'],
            'lines.*.quantity'           => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,10})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'lines.*.unit_code'          => [
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
