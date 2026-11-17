<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Illuminate\Support\Str;
use Lahatre\Sale\Data\PlannedFulfillmentLine;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Sale\Models\FulfillmentLine;
use Lahatre\Sale\Models\Sale;
use Lahatre\Shared\Services\BusinessNumberService;

/** Writes fulfillments from checked lines, inside the caller's transaction and lock. */
final readonly class FulfillmentWriter
{
    public function __construct(
        private FulfillmentStockService $fulfillmentStockService,
    ) {}

    /** @param list<PlannedFulfillmentLine> $planned */
    public function createDraft(Sale $sale, array $planned): Fulfillment
    {
        $fulfillment = Fulfillment::query()->create([
            'organization_id' => $sale->organization_id,
            'sale_id'         => $sale->id,
            'number'          => BusinessNumberService::next('sale_fulfillment'),
            'status'          => FulfillmentStatus::Draft,
        ]);
        $this->replaceLines($fulfillment, $planned);

        return $fulfillment;
    }

    /**
     * Takes the lines out of stock and records the fulfillment as completed, with what each line cost.
     *
     * @param  list<PlannedFulfillmentLine>  $planned
     *
     * @throws SaleException If the stock is lacking by the time it is locked.
     */
    public function complete(Fulfillment $fulfillment, array $planned): Fulfillment
    {
        $moved = $this->fulfillmentStockService->takeOut($fulfillment->id, $planned);
        $this->replaceLines($fulfillment, $planned, $moved['costs']);
        $fulfillment->fill([
            'status'                   => FulfillmentStatus::Completed,
            'inventory_transaction_id' => $moved['transaction_id'],
            'completed_at'             => now(),
        ])->save();

        return $fulfillment;
    }

    /**
     * @param  list<PlannedFulfillmentLine>  $planned
     * @param  array<int, int>  $costs  by planned index
     */
    public function replaceLines(Fulfillment $fulfillment, array $planned, array $costs = []): void
    {
        FulfillmentLine::query()
            ->where('organization_id', $fulfillment->organization_id)
            ->where('fulfillment_id', $fulfillment->id)
            ->delete();

        $now = now();
        $rows = [];
        foreach ($planned as $index => $line) {
            $rows[] = [
                'id'                => (string) Str::uuid7(),
                'organization_id'   => $fulfillment->organization_id,
                'fulfillment_id'    => $fulfillment->id,
                'sale_line_id'      => $line->saleLine->id,
                'stock_location_id' => $line->warehouse?->id,
                'quantity'          => $line->quantity,
                'stock_ids'         => $line->stockIds === null ? null : json_encode($line->stockIds, JSON_THROW_ON_ERROR),
                'total_cost'        => $costs[$index] ?? null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        if ($rows !== []) {
            FulfillmentLine::query()->insert($rows);
        }
    }
}
