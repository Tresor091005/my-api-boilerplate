<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class GoogleAuthenticationData
{
    private function __construct(
        public string $challengeId,
        public string $credential,
        public ?string $firstName,
        public ?string $lastName,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['challenge_id'], $data['credential'], $data['first_name'] ?? null, $data['last_name'] ?? null);
    }
}
