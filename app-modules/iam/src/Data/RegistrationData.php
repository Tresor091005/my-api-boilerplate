<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class RegistrationData
{
    /** @param array{name: string, currency_code: string, timezone: string} $organization */
    private function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public string $email,
        public string $token,
        public ?string $password,
        public array $organization,
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
            $data['organization'],
        );
    }
}
