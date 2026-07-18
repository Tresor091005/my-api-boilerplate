<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Lahatre\Inventory\DTO\MovementDataDTO;
use Lahatre\Inventory\DTO\TransactionDataDTO;

final class TransactionPayloadHasher
{
    public function hash(TransactionDataDTO $transaction): string
    {
        $payload = [
            'reference_type'   => $transaction->reference_type,
            'reference_id'     => $transaction->reference_id,
            'transaction_type' => $transaction->transaction_type->value,
            'metadata'         => $transaction->metadata,
            'movements'        => $transaction->movements
                ->map(fn (MovementDataDTO $movement): array => [
                    'item_id'         => $movement->item_id,
                    'location_id'     => $movement->location_id,
                    'to_location_id'  => $movement->to_location_id,
                    'type'            => $movement->type?->value,
                    'quantity'        => $movement->quantity,
                    'unit_code'       => $movement->unit_code,
                    'total_cost'      => $movement->total_cost,
                    'currency_code'   => $movement->currency_code,
                    'expiration_date' => $movement->expiration_date?->toISOString(),
                    'strategy'        => $movement->strategy?->value,
                    'stock_ids'       => $movement->stock_ids,
                    'metadata'        => $movement->metadata,
                    'stock_metadata'  => $movement->stock_metadata,
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
