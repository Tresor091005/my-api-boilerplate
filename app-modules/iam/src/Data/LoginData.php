<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class LoginData
{
    private function __construct(
        public string $challengeId,
        public string $code,
        public ?string $firstName,
        public ?string $lastName,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['challenge_id'], $data['code'], $data['first_name'] ?? null, $data['last_name'] ?? null);
    }
}
