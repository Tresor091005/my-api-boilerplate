<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Illuminate\Support\Collection;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Master\Contracts\MasterInterface;

readonly class TransactionDataDTO
{
    /**
     * @param  Collection<int, MovementDataDTO>  $movements
     */
    public function __construct(
        public string $idempotency_key,
        public string $reference_type,
        public string $reference_id,
        public TransactionType $transaction_type,
        public Collection $movements,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data, MasterInterface $masterInterface, bool $costsInMinor = false): self
    {
        return new self(
            idempotency_key: $data['idempotency_key'],
            reference_type: $data['reference_type'],
            reference_id: $data['reference_id'],
            transaction_type: is_string($data['transaction_type'])
                ? TransactionType::from($data['transaction_type'])
                : $data['transaction_type'],
            movements: collect($data['movements'])->map(fn (array $m): MovementDataDTO => MovementDataDTO::fromArray($m, $masterInterface, $costsInMinor)),
            metadata: $data['metadata'] ?? null,
        );
    }
}
