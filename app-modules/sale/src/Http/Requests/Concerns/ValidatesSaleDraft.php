<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Shared\Rules\BulkExists;
use Lahatre\Shared\Rules\YmdDate;

/** Shared by draft creation and replacement; the service checks what can be sold. */
trait ValidatesSaleDraft
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function draftRules(): array
    {
        $organizationId = currentOrganizationId();

        return [
            'customer_id' => [
                'nullable',
                'uuid',
                Rule::exists('customer_customers', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'mode'                     => ['nullable', Rule::enum(SaleMode::class)],
            'required_payment_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            // today in the organization's timezone
            'payment_due_date' => [
                'bail',
                'nullable',
                new YmdDate,
                'after_or_equal:'.app(OrganizationInterface::class)->today($organizationId)->toDateString(),
            ],
            'lines' => [
                'required',
                'array',
                'min:1',
                'max:'.config('child-limits.sale_lines.sale_id'),
                new BulkExists('catalog_items', 'id', 'catalog_item_id', 'uuid', true, [
                    'organization_id' => $organizationId,
                ]),
                // the sale units of all lines in one lookup: a system unit or one of the organization
                new BulkExists('master_units', 'code', 'unit_code', 'string', true, [
                    fn (Builder $query): Builder => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId),
                ]),
                new BulkExists('catalog_price_levels', 'id', 'price_level_id', 'uuid', true, [
                    'organization_id' => $organizationId,
                ]),
            ],
            'lines.*'                 => ['required', 'array'],
            'lines.*.catalog_item_id' => ['required', 'uuid'],
            'lines.*.unit_code'       => ['required', 'string', 'max:100'],
            'lines.*.quantity'        => ['required', 'integer', 'min:1', 'max:1000000000'],
            'lines.*.price_level_id'  => ['required', 'uuid'],
            // the unit price read at that level, checked against the catalog by the service
            'lines.*.unit_amount' => ['required', 'decimal:0,8', 'min:0', 'max:1000000000000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $lines = $this->input('lines');

                if (!is_array($lines)) {
                    return;
                }

                $seen = [];

                foreach ($lines as $index => $line) {
                    if (!is_array($line) || !is_string($line['catalog_item_id'] ?? null) || !is_string($line['unit_code'] ?? null)) {
                        continue;
                    }

                    $key = "{$line['catalog_item_id']}:{$line['unit_code']}";

                    if (isset($seen[$key])) {
                        $validator->errors()->add("lines.{$index}.catalog_item_id", __('sale::validation.line_duplicate'));
                    }
                    $seen[$key] = true;
                }
            },
        ];
    }
}
