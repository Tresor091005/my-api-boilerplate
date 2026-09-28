<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Data;

final readonly class CommitmentData
{
    private function __construct(
        public string $serviceId,
        public string $customerId,
        public string $clientEmail,
        public ?string $title,
        public ?string $terms,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['service_id'], $data['customer_id'], $data['client_email'], $data['title'] ?? null, $data['terms'] ?? null);
    }
}
