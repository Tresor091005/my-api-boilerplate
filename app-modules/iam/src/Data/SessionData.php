<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class SessionData
{
    private function __construct(public ?string $ipAddress, public ?string $userAgent) {}

    /** @param array{ip_address?: string|null, user_agent?: string|null} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['ip_address'] ?? null, isset($data['user_agent']) ? mb_substr($data['user_agent'], 0, 1000) : null);
    }

    /** @return array{ip_address: string|null, user_agent: string|null} */
    public function toArray(): array
    {
        return ['ip_address' => $this->ipAddress, 'user_agent' => $this->userAgent];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }
}
