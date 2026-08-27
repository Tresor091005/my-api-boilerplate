<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Illuminate\Support\Collection;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Master\Contracts\MasterInterface;

final readonly class TransactionData
{
    /**
     * @param  Collection<int, MovementData>  $movements
     * @param  array<string, mixed>|null  $metadata
     */
    private function __construct(
        public string $idempotencyKey,
        public string $referenceType,
        public string $referenceId,
        public TransactionType $transactionType,
        public Collection $movements,
        public ?array $metadata,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, MasterInterface $masterInterface, bool $costsInMinor = false): self
    {
        $transactionType = $data['transaction_type'];

        return new self(
            idempotencyKey: $data['idempotency_key'],
            referenceType: $data['reference_type'],
            referenceId: $data['reference_id'],
            transactionType: is_string($transactionType) ? TransactionType::from($transactionType) : $transactionType,
            movements: collect($data['movements'])->map(
                fn (array $movement): MovementData => MovementData::fromArray($movement, $masterInterface, $costsInMinor),
            ),
            metadata: $data['metadata'] ?? null,
        );
    }

    /** @param Collection<int, MovementData> $movements */
    public function withMovements(Collection $movements): self
    {
        return new self(
            idempotencyKey: $this->idempotencyKey,
            referenceType: $this->referenceType,
            referenceId: $this->referenceId,
            transactionType: $this->transactionType,
            movements: $movements,
            metadata: $this->metadata,
        );
    }
}
