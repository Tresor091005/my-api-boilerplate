<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxDependencyOperator;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;

/**
 * Create and update share one complete definition: an update replaces the
 * brackets and dependencies. Omitted optional settings take their defaults.
 * Cross-row checks (bracket tiling, dependency targets, cycles) belong to the
 * service.
 */
class TaxRequest extends FormRequest
{
    private const string DECIMAL = 'regex:/^\d{1,18}(\.\d{1,6})?$/';

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::toUpper(Str::sanitize($this->input('code')))]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::sanitize($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code'             => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'],
            'name'             => ['required', 'string', 'max:100'],
            'kind'             => [Rule::enum(TaxKind::class)],
            'calculation_type' => ['required', Rule::enum(TaxCalculationType::class)],
            'fixed_mode'       => ['nullable', Rule::enum(TaxFixedMode::class)],
            'tax_unit_code'    => [
                'nullable',
                'string',
                'max:100',
                Rule::exists('master_units', 'code')
                    ->whereNull('deleted_at')
                    ->where(fn (Builder $query): Builder => $query
                        ->whereNull('organization_id')
                        ->orWhere('organization_id', currentOrganizationId())),
            ],
            'base_type'                        => [Rule::enum(TaxBaseType::class)],
            'is_progressive'                   => ['boolean'],
            'bracket_mode'                     => ['nullable', Rule::enum(TaxBracketMode::class)],
            'rounding_mode'                    => [Rule::enum(TaxRoundingMode::class)],
            'is_active'                        => ['boolean'],
            'brackets'                         => ['required', 'array', 'min:1', 'max:50'],
            'brackets.*'                       => ['required', 'array'],
            'brackets.*.id'                    => ['nullable', 'uuid', 'distinct'],
            'brackets.*.base_min'              => ['required', 'string', self::DECIMAL],
            'brackets.*.base_max'              => ['nullable', 'string', self::DECIMAL],
            'brackets.*.value'                 => ['required', 'string', self::DECIMAL],
            'brackets.*.tax_amount_min'        => ['nullable', 'string', self::DECIMAL],
            'brackets.*.tax_amount_max'        => ['nullable', 'string', self::DECIMAL],
            'dependencies'                     => ['array', 'max:20'],
            'dependencies.*'                   => ['required', 'array'],
            'dependencies.*.depends_on_tax_id' => ['required', 'uuid', 'distinct'],
            'dependencies.*.operator'          => ['required', Rule::enum(TaxDependencyOperator::class)],
        ];
    }
}
