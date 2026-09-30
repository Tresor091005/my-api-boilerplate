<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class InvitationAcceptanceData
{
    private function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public string $email,
        public string $token,
        public ?string $password,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['email'],
            $data['token'],
            $data['password'] ?? null,
        );
    }
}
