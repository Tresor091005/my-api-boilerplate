<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Lahatre\Inventory\Data\MovementData;
use Lahatre\Inventory\Data\TransactionData;

final class TransactionPayloadHasher
{
    public function hash(TransactionData $transaction): string
    {
        $payload = [
            'reference_type'   => $transaction->referenceType,
            'reference_id'     => $transaction->referenceId,
            'transaction_type' => $transaction->transactionType->value,
            'metadata'         => $transaction->metadata,
            'movements'        => $transaction->movements
                ->map(fn (MovementData $movement): array => [
                    'item_id'         => $movement->itemId,
                    'location_id'     => $movement->locationId,
                    'to_location_id'  => $movement->toLocationId,
                    'type'            => $movement->type?->value,
                    'quantity'        => $movement->quantity,
                    'unit_code'       => $movement->unitCode,
                    'total_cost'      => $movement->totalCost,
                    'currency_code'   => $movement->currencyCode,
                    'expiration_date' => $movement->expirationDate?->toISOString(),
                    'strategy'        => $movement->strategy?->value,
                    'stock_ids'       => $movement->stockIds,
                    'metadata'        => $movement->metadata,
                    'stock_metadata'  => $movement->stockMetadata,
                ])
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (!is_array($value)) {
            return $value;
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[$key] = $this->canonicalize($item);
        }

        if (!array_is_list($canonical)) {
            ksort($canonical);
        }

        return $canonical;
    }
}
