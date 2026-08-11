<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class ResetPasswordData
{
    private function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            token: $data['token'],
            password: $data['password'],
        );
    }
}
