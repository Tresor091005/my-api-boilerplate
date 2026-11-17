<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Where each line of a sale leaves from. What the sources must add up to, whether the warehouses and
 * lots belong to the organization and hold the stock is checked by the service, under the sale's lock.
 */
trait ValidatesFulfillmentLines
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function fulfillmentLinesRules(bool $required): array
    {
        return [
            'lines'                               => $required ? ['required', 'array', 'min:1', 'max:250'] : ['array', 'max:250'],
            'lines.*'                             => ['required', 'array'],
            'lines.*.sale_line_id'                => ['required', 'uuid'],
            'lines.*.sources'                     => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.sources.*'                   => ['required', 'array'],
            'lines.*.sources.*.stock_location_id' => ['nullable', 'uuid'],
            'lines.*.sources.*.quantity'          => ['required', 'integer', 'min:1', 'max:1000000000'],
            'lines.*.sources.*.stock_ids'         => ['nullable', 'array', 'max:100'],
            'lines.*.sources.*.stock_ids.*'       => ['uuid', 'distinct'],
        ];
    }
}
