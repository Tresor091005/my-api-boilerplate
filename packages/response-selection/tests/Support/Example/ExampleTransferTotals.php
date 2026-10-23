<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\FieldComputer;

/**
 * Line count and total quantity of every transfer of a node, in one aggregate query selecting only
 * the requested aggregates.
 */
final class ExampleTransferTotals implements FieldComputer
{
    private const AGGREGATES = [
        'line_count'     => 'count(*)',
        'total_quantity' => 'coalesce(sum(quantity), 0)',
    ];

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        $select = collect($fields)->map(fn (string $field): string => self::AGGREGATES[$field].' as '.$field)->implode(', ');
        $rows = DB::table('response_selection_example_transfer_lines')
            ->whereIn('transfer_id', $models->modelKeys())
            ->groupBy('transfer_id')
            ->selectRaw("transfer_id, {$select}")
            ->get()
            ->keyBy('transfer_id');
        $values = [];

        foreach ($models as $transfer) {
            $row = $rows->get($transfer->getKey());

            foreach ($fields as $field) {
                $values[$transfer->getKey()][$field] = (int) ($row->{$field} ?? 0);
            }
        }

        return $values;
    }
}
